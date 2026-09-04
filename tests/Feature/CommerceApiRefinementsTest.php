<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Feature;

use Karnoweb\Commerce\Contracts\CommerceContextResolverContract;
use Karnoweb\Commerce\DTOs\CheckoutResult;
use Karnoweb\Commerce\DTOs\CheckoutResultWithPayments;
use Karnoweb\Commerce\DTOs\ReturnResult;
use Karnoweb\Commerce\Enums\FinancialStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;
use Karnoweb\Commerce\Exceptions\InvalidFinancialTransition;
use Karnoweb\Commerce\Facades\Commerce;
use Karnoweb\Commerce\Models\DocumentSequence;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\OrderLine;
use Karnoweb\Commerce\Models\Payment;
use Karnoweb\Commerce\Models\PaymentMethod;
use Karnoweb\Commerce\Services\OrderService;
use Karnoweb\Commerce\Support\FinancialStateMachine;
use Karnoweb\Commerce\Tests\Support\ConfiguresCommerceModels;
use Karnoweb\Commerce\Tests\TestCase;

/**
 * Coverage for the production-facing API refinements: sequential document
 * numbers, finalizeWithPayments + extra attributes, return DTO, financial
 * state machine, and free-form workflow_status.
 */
final class CommerceApiRefinementsTest extends TestCase
{
    use ConfiguresCommerceModels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCommerceModels();

        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_checkout_finalize_always_creates_an_invoice(): void
    {
        $userId = 1;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);

        $result = Commerce::checkout()->forUser($userId)->finalize();

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertInstanceOf(Invoice::class, $result->invoice);
        $this->assertSame($result->order->id, $result->invoice->order_id);
        $this->assertSame((int) $result->order->total_amount, (int) $result->invoice->amount);
        $this->assertSame('issued', $result->invoice->status);
        $this->assertSame('issued', $result->invoice->financial_status);
        $this->assertSame(0, OrderLine::query()->carts()->where('user_id', $userId)->count());
    }

    public function test_finalize_with_payments_creates_pending_records_and_stores_extra(): void
    {
        $userId = 2;
        $method = PaymentMethod::query()->create(['provider' => 'cash', 'published' => true]);

        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 300_000, itemId: 1);

        $result = Commerce::checkout()
            ->forUser($userId)
            ->branchId(3)
            ->idempotencyKey('checkout:user:2:split')
            ->finalizeWithPayments([
                [
                    'method_id' => $method->id,
                    'type' => PaymentTypeEnum::CASH,
                    'amount' => 200_000,
                    'extra' => ['cashbox_id' => 5, 'cashier_id' => 12],
                ],
                [
                    'method_id' => $method->id,
                    'type' => PaymentTypeEnum::CARD_TO_CARD,
                    'amount' => 100_000,
                    'extra' => ['terminal_id' => 9],
                ],
            ]);

        $this->assertInstanceOf(CheckoutResultWithPayments::class, $result);
        $this->assertCount(2, $result->payments);
        $this->assertSame(PaymentStatusEnum::PENDING, $result->payments[0]->status);
        $this->assertSame(PaymentStatusEnum::PENDING, $result->payments[1]->status);
        $this->assertSame(['cashbox_id' => 5, 'cashier_id' => 12], $result->payments[0]->extra_attributes);
        $this->assertSame(['terminal_id' => 9], $result->payments[1]->extra_attributes);
        $this->assertSame($result->invoice->id, $result->payments[0]->invoice_id);
        $this->assertSame($result->order->id, $result->payments[0]->order_id);
        $this->assertSame(FinancialStatusEnum::PENDING, $result->order->financial_status);

        $retry = Commerce::checkout()
            ->forUser($userId)
            ->branchId(3)
            ->idempotencyKey('checkout:user:2:split')
            ->finalizeWithPayments([
                ['method_id' => $method->id, 'type' => PaymentTypeEnum::CASH, 'amount' => 200_000],
                ['method_id' => $method->id, 'type' => PaymentTypeEnum::CARD_TO_CARD, 'amount' => 100_000],
            ]);

        $this->assertSame($result->order->id, $retry->order->id);
        $this->assertSame($result->payments[0]->id, $retry->payments[0]->id);
        $this->assertSame(2, Payment::query()->where('invoice_id', $result->invoice->id)->count());
    }

    public function test_confirm_payment_updates_financial_status_to_paid(): void
    {
        $userId = 3;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);
        $result = Commerce::checkout()->forUser($userId)->finalize();

        $payment = Commerce::payments()
            ->forInvoice($result->invoice)
            ->amount((int) $result->invoice->amount)
            ->extra(['cashbox_id' => 1])
            ->initiate();

        $this->assertSame($result->order->id, $payment->order_id, 'forOrder() is optional — order_id is copied from the invoice.');
        $this->assertSame(['cashbox_id' => 1], $payment->extra_attributes);

        Commerce::payments()->confirm($payment, gateway: 'cash', trackingCode: 'TRK-FIN');

        $this->assertSame(FinancialStatusEnum::PAID, $result->order->refresh()->financial_status);
        $this->assertNotNull($result->order->paid_at);
        $this->assertSame('paid', $result->invoice->refresh()->financial_status);
        $this->assertSame('paid', $result->invoice->status);
        $this->assertSame(PaymentStatusEnum::PAID, $payment->refresh()->status);
    }

    public function test_returns_refund_to_wallet_result_includes_wallet_transaction(): void
    {
        $userId = 4;
        $branchId = 3;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 80_000, itemId: 1);
        $checkout = Commerce::checkout()->forUser($userId)->branchId($branchId)->finalize();
        $payment = Commerce::payments()->forInvoice($checkout->invoice)->amount((int) $checkout->invoice->amount)->initiate();
        Commerce::payments()->confirm($payment, gateway: 'cash', trackingCode: 'TRK-RET');

        $line = OrderLine::query()->where('order_id', $checkout->order->id)->firstOrFail();

        $result = Commerce::returns()
            ->forOrder($checkout->order)
            ->addLine(orderLineId: $line->id, quantity: 1)
            ->finalizeRefundToWalletResult(userId: $userId, branchId: $branchId);

        $this->assertInstanceOf(ReturnResult::class, $result);
        $this->assertSame($checkout->order->id, $result->orderReturn->order_id);
        $this->assertSame(80_000, (int) $result->orderReturn->total_amount);
        $this->assertSame($result->wallet->id, $result->walletTransaction->wallet_id);
        $this->assertSame(80_000, (int) $result->walletTransaction->amount);
        $this->assertSame(1, (int) $result->walletTransaction->sign);
        $this->assertSame($branchId, (int) $result->wallet->branch_id);
        $this->assertSame(FinancialStatusEnum::REFUNDED, $checkout->order->refresh()->financial_status);
    }

    public function test_invalid_financial_transitions_throw(): void
    {
        $this->assertFalse(FinancialStateMachine::canTransition('paid', 'pending'));
        $this->assertFalse(FinancialStateMachine::canTransition('refunded', 'paid'));
        $this->assertTrue(FinancialStateMachine::canTransition('pending', 'paid'));
        $this->assertTrue(FinancialStateMachine::canTransition('pending', 'cancelled'));
        $this->assertTrue(FinancialStateMachine::canTransition('paid', 'refunded'));

        $userId = 5;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 50_000, itemId: 1);
        $checkout = Commerce::checkout()->forUser($userId)->finalize();
        $payment = Commerce::payments()->forInvoice($checkout->invoice)->amount((int) $checkout->invoice->amount)->initiate();
        Commerce::payments()->confirm($payment, gateway: 'cash', trackingCode: 'TRK-SM');

        $this->expectException(InvalidFinancialTransition::class);
        Commerce::orders()->cancel($checkout->order->id);
    }

    public function test_cannot_initiate_payment_on_a_refunded_order(): void
    {
        $userId = 6;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 40_000, itemId: 1);
        $checkout = Commerce::checkout()->forUser($userId)->finalize();
        $payment = Commerce::payments()->forInvoice($checkout->invoice)->amount((int) $checkout->invoice->amount)->initiate();
        Commerce::payments()->confirm($payment, gateway: 'cash', trackingCode: 'TRK-SM2');

        $line = OrderLine::query()->where('order_id', $checkout->order->id)->firstOrFail();
        Commerce::returns()
            ->forOrder($checkout->order)
            ->addLine(orderLineId: $line->id, quantity: 1)
            ->finalizeRefundToWallet(userId: $userId);

        $this->assertSame(FinancialStatusEnum::REFUNDED, $checkout->order->refresh()->financial_status);

        $this->expectException(InvalidFinancialTransition::class);
        Commerce::payments()->forInvoice($checkout->invoice->refresh())->amount(40_000)->initiate();
    }

    public function test_cannot_transition_paid_order_back_to_pending(): void
    {
        $userId = 7;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 40_000, itemId: 1);
        $checkout = Commerce::checkout()->forUser($userId)->finalize();
        $payment = Commerce::payments()->forInvoice($checkout->invoice)->amount((int) $checkout->invoice->amount)->initiate();
        Commerce::payments()->confirm($payment, gateway: 'cash', trackingCode: 'TRK-SM3');

        $this->expectException(InvalidFinancialTransition::class);
        app(OrderService::class)->transitionTo($checkout->order->refresh(), FinancialStatusEnum::PENDING);
    }

    public function test_workflow_status_is_free_form_and_does_not_touch_financial_status(): void
    {
        $userId = 8;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 10_000, itemId: 1);
        $order = Commerce::checkout()->forUser($userId)->finalize()->order;

        $updated = Commerce::orders()->setWorkflowStatus($order->id, 'cooking');

        $this->assertSame('cooking', $updated->workflow_status);
        $this->assertSame(FinancialStatusEnum::PENDING, $updated->financial_status);

        $cancelled = Commerce::orders()->cancel($order->id);
        $this->assertSame(FinancialStatusEnum::CANCELLED, $cancelled->financial_status);
        $this->assertSame('cooking', $cancelled->workflow_status);
    }

    public function test_order_and_invoice_numbers_are_sequential_overridable_and_configurable(): void
    {
        $year = (int) now()->year;

        Commerce::cart()->forUser(21)->addLine(itemType: 'shop.product', name: 'A', quantity: 1, unitPrice: 10_000, itemId: 1);
        $first = Commerce::checkout()->forUser(21)->branchId(3)->finalize();

        Commerce::cart()->forUser(22)->addLine(itemType: 'shop.product', name: 'B', quantity: 1, unitPrice: 10_000, itemId: 1);
        $second = Commerce::checkout()->forUser(22)->branchId(3)->finalize();

        $this->assertSame("ORD-{$year}-3-000001", $first->order->order_number);
        $this->assertSame("ORD-{$year}-3-000002", $second->order->order_number);
        $this->assertSame("INV-{$year}-3-000001", $first->invoice->invoice_number);
        $this->assertSame("INV-{$year}-3-000002", $second->invoice->invoice_number);

        Commerce::cart()->forUser(23)->addLine(itemType: 'shop.product', name: 'C', quantity: 1, unitPrice: 10_000, itemId: 1);
        $overridden = Commerce::checkout()
            ->forUser(23)
            ->orderNumber('CUSTOM-ORD-9')
            ->invoiceNumber('CUSTOM-INV-9')
            ->finalize();

        $this->assertSame('CUSTOM-ORD-9', $overridden->order->order_number);
        $this->assertSame('CUSTOM-INV-9', $overridden->invoice->invoice_number);

        config([
            'commerce.numbers.order.format' => 'X-{year}-{sequence}',
            'commerce.numbers.invoice.format' => 'Y-{year}-{sequence}',
        ]);

        Commerce::cart()->forUser(24)->addLine(itemType: 'shop.product', name: 'D', quantity: 1, unitPrice: 10_000, itemId: 1);
        $custom = Commerce::checkout()->forUser(24)->finalize();

        $this->assertSame("X-{$year}-000001", $custom->order->order_number);
        $this->assertSame("Y-{$year}-000001", $custom->invoice->invoice_number);

        $this->assertSame(1, DocumentSequence::query()->where('key', 'order_number')->where('scope_branch_id', 3)->count());
    }

    public function test_checkout_resolves_branch_id_from_context_resolver_when_omitted(): void
    {
        $this->app->instance(CommerceContextResolverContract::class, new class implements CommerceContextResolverContract
        {
            public function branchId(): int|string|null
            {
                return 77;
            }
        });

        Commerce::cart()->forUser(30)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 10_000, itemId: 1);

        $result = Commerce::checkout()->forUser(30)->branchId(null)->finalize();

        $this->assertSame(77, $result->order->branch_id);
    }
}
