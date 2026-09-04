<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Karnoweb\Commerce\DTOs\CheckoutResult;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;
use Karnoweb\Commerce\Events\InvoiceFullyPaid;
use Karnoweb\Commerce\Events\InvoiceIssued;
use Karnoweb\Commerce\Events\OrderCreated;
use Karnoweb\Commerce\Events\OrderPaid;
use Karnoweb\Commerce\Events\PaymentConfirmed;
use Karnoweb\Commerce\Events\RefundCreated;
use Karnoweb\Commerce\Exceptions\CannotCheckoutEmptyCart;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Facades\Commerce;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderLine;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Models\PaymentMethod;
use Karnoweb\Commerce\Models\Transaction;
use Karnoweb\Commerce\Models\WalletTransaction;
use Karnoweb\Commerce\Tests\Support\ConfiguresCommerceModels;
use Karnoweb\Commerce\Tests\TestCase;

/**
 * End-to-end coverage of the canonical Facade flow: cart -> checkout
 * (finalize, mandatory invoice) -> payment (invoice-centric) -> confirm ->
 * legacy amount-only refund. Runs against a standalone sqlite install — no
 * host models, no product_id anywhere.
 */
final class CommerceFacadeEndToEndTest extends TestCase
{
    use ConfiguresCommerceModels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCommerceModels();

        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_full_cart_to_refund_flow_via_facade(): void
    {
        Event::fake();

        $userId = 9001;
        $branchId = 3;

        Commerce::cart()
            ->forUser($userId)
            ->branchId($branchId)
            ->addLine(
                itemType: 'shop.product',
                name: 'Coffee Beans 1kg',
                quantity: 2,
                unitPrice: 1_000_000,
                itemId: 501,
                sku: 'COF-1KG',
                uomCode: 'kg',
            );

        $cartLines = Commerce::cart()->forUser($userId)->items();
        $this->assertCount(1, $cartLines);
        $this->assertSame('Coffee Beans 1kg', $cartLines->first()->item_name);
        $this->assertSame('shop.product', $cartLines->first()->item_type);
        $this->assertSame(501, $cartLines->first()->item_id);

        $result = Commerce::checkout()
            ->forUser($userId)
            ->branchId($branchId)
            ->shippingAmount(50_000)
            ->idempotencyKey('checkout:user:9001:cart:active')
            ->finalize();

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $order = $result->order;
        $invoice = $result->invoice;

        $this->assertInstanceOf(Order::class, $order);
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(OrderStatusEnum::PENDING, $order->status);
        $this->assertSame($branchId, $order->branch_id);
        $this->assertSame(2_050_000, (int) $order->total_amount); // 2 * 1,000,000 + 50,000 shipping
        $this->assertSame((int) $order->total_amount, (int) $invoice->amount, 'The mandatory invoice must mirror the order total.');
        $this->assertSame($order->id, $invoice->order_id);

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($order): bool {
            return (string) $event->orderId === (string) $order->id;
        });
        Event::assertDispatched(InvoiceIssued::class, function (InvoiceIssued $event) use ($invoice): bool {
            return (string) $event->invoiceId === (string) $invoice->id;
        });

        // Cart lines are attached to the order, not left dangling.
        $this->assertSame(0, OrderLine::query()->carts()->where('user_id', $userId)->count());
        $this->assertSame(1, OrderLine::query()->where('order_id', $order->id)->count());

        $paymentMethod = PaymentMethod::query()->create(['provider' => 'zarinpal', 'published' => true]);

        $payment = Commerce::payments()
            ->forInvoice($invoice)
            ->forOrder($order)
            ->methodId($paymentMethod->id)
            ->type(PaymentTypeEnum::ONLINE)
            ->amount((int) $invoice->amount)
            ->idempotencyKey('pay:invoice:'.$invoice->id.':attempt:1')
            ->initiate();

        $this->assertSame(PaymentStatusEnum::PENDING, $payment->status);
        $this->assertSame($invoice->id, $payment->invoice_id);

        $confirmed = Commerce::payments()->confirm(
            payment: $payment,
            gateway: 'zarinpal',
            refId: 'REF-123',
            trackingCode: 'TRK-777',
            paidAt: now(),
            gatewayPayload: ['raw' => '...'],
        );

        $this->assertSame(PaymentStatusEnum::PAID, $confirmed->status);

        $order->refresh();
        $invoice->refresh();

        $this->assertSame(OrderStatusEnum::PAID, $order->status);
        $this->assertSame('paid', $invoice->status);
        $this->assertSame(1, Transaction::query()->where('payment_id', $payment->id)->where('tracking_code', 'TRK-777')->count());

        Event::assertDispatched(OrderPaid::class, function (OrderPaid $event) use ($order): bool {
            return (string) $event->orderId === (string) $order->id;
        });
        Event::assertDispatched(InvoiceFullyPaid::class, function (InvoiceFullyPaid $event) use ($invoice): bool {
            return (string) $event->invoiceId === (string) $invoice->id;
        });
        Event::assertDispatched(PaymentConfirmed::class, function (PaymentConfirmed $event) use ($payment): bool {
            return (string) $event->paymentId === (string) $payment->id;
        });

        // Partial refund to wallet (legacy amount-only flow).
        $orderReturn = Commerce::refund()
            ->forOrder($order)
            ->amount(1_000_000)
            ->reason('customer_return')
            ->toWallet(userId: $userId, branchId: $branchId)
            ->idempotencyKey('refund:order:'.$order->id.':amount:1000000')
            ->process();

        $this->assertInstanceOf(OrderReturn::class, $orderReturn);
        $this->assertSame($order->id, $orderReturn->order_id);
        $this->assertSame(1_000_000, (int) $orderReturn->total_amount);

        $order->refresh();
        $this->assertSame(OrderStatusEnum::PAID, $order->status, 'Partial refund must not flip the order to REFUNDED.');

        $walletTransaction = WalletTransaction::query()
            ->where('transactionable_type', $orderReturn->getMorphClass())
            ->where('transactionable_id', $orderReturn->id)
            ->first();

        $this->assertNotNull($walletTransaction, 'Refund must credit a WalletTransaction.');
        $this->assertSame(1_000_000, (int) $walletTransaction->amount);
        $this->assertSame(1, $walletTransaction->sign);

        Event::assertDispatched(RefundCreated::class, function (RefundCreated $event) use ($order, $orderReturn): bool {
            return (string) $event->orderId === (string) $order->id
                && (string) $event->orderReturnId === (string) $orderReturn->id;
        });
    }

    public function test_finalize_throws_when_cart_is_empty(): void
    {
        $this->expectException(CannotCheckoutEmptyCart::class);

        Commerce::checkout()->forUser(4242)->finalize();
    }

    public function test_place_is_an_alias_for_finalize(): void
    {
        $userId = 1;

        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);

        $result = Commerce::checkout()->forUser($userId)->place();

        $this->assertInstanceOf(CheckoutResult::class, $result);
        $this->assertSame(100_000, (int) $result->order->total_amount);
    }

    public function test_finalize_is_idempotent_for_retries_with_the_same_key(): void
    {
        $userId = 1;

        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);

        $first = Commerce::checkout()->forUser($userId)->idempotencyKey('checkout:retry')->finalize();
        $second = Commerce::checkout()->forUser($userId)->idempotencyKey('checkout:retry')->finalize();

        $this->assertSame($first->order->id, $second->order->id);
        $this->assertSame($first->invoice->id, $second->invoice->id);
        $this->assertSame(1, OrderLine::query()->where('order_id', $first->order->id)->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_finalize_throws_idempotency_conflict_for_a_different_user_with_the_same_key(): void
    {
        Commerce::cart()->forUser(1)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);
        Commerce::checkout()->forUser(1)->idempotencyKey('checkout:shared')->finalize();

        Commerce::cart()->forUser(2)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);

        $this->expectException(IdempotencyConflict::class);

        Commerce::checkout()->forUser(2)->idempotencyKey('checkout:shared')->finalize();
    }

    public function test_confirm_is_idempotent_for_retries_with_the_same_tracking_code(): void
    {
        $userId = 1;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);
        $result = Commerce::checkout()->forUser($userId)->finalize();

        $payment = Commerce::payments()->forInvoice($result->invoice)->amount((int) $result->invoice->amount)->initiate();

        $first = Commerce::payments()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-SAME');
        $second = Commerce::payments()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-SAME');

        $this->assertSame(PaymentStatusEnum::PAID, $first->status);
        $this->assertSame(PaymentStatusEnum::PAID, $second->status);
        $this->assertSame(1, Transaction::query()->where('payment_id', $payment->id)->count());
    }
}
