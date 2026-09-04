<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Karnoweb\Commerce\Database\Seeders\CommerceSeeder;
use Karnoweb\Commerce\Enums\FinancialStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Events\PaymentInitiated;
use Karnoweb\Commerce\Events\ReturnCreated;
use Karnoweb\Commerce\Exceptions\CannotReturnWithoutLines;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Exceptions\ReturnQuantityExceedsAvailable;
use Karnoweb\Commerce\Facades\Commerce;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderLine;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Models\OrderReturnLine;
use Karnoweb\Commerce\Models\PaymentMethod;
use Karnoweb\Commerce\Models\ReturnReason;
use Karnoweb\Commerce\Models\WalletTransaction;
use Karnoweb\Commerce\Tests\Support\ConfiguresCommerceModels;
use Karnoweb\Commerce\Tests\TestCase;

/**
 * Coverage for generic lines (item_type/item_id/item_name — no product_id
 * anywhere), document_adjustments (shipping/tax/discount shortcuts +
 * custom keys), document_dimensions (salesUnitId/warehouseId/addDimension),
 * standalone invoices, normalized return reasons, and quantity-based
 * returns tied to original sale lines. Runs against a standalone sqlite
 * install — no host models.
 */
final class CommerceGenericLineItemsAndReturnsTest extends TestCase
{
    use ConfiguresCommerceModels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureCommerceModels();

        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_cart_supports_generic_product_service_text_and_custom_lines(): void
    {
        $userId = 1;

        Commerce::cart()
            ->forUser($userId)
            ->addLine(itemType: 'shop.product', name: 'Coffee Beans 1kg', quantity: 2, unitPrice: 1_000_000, itemId: 501, sku: 'COF-1KG', uomCode: 'kg')
            ->addProductItem(itemId: 502, name: 'Legacy alias product', quantity: 1, unitPrice: 10_000)
            ->addServiceItem(name: 'Installation service', quantity: 1, unitPrice: 200_000)
            ->addTextItem(description: 'Special packaging', amount: 50_000)
            ->addCustomItem(itemType: 'gift_card', itemId: 42, name: 'Gift card', quantity: 1, unitPrice: 100_000);

        $lines = Commerce::cart()->forUser($userId)->items();

        $this->assertCount(5, $lines);

        $product = $lines->firstWhere('item_id', 501);
        $this->assertNotNull($product);
        $this->assertSame('shop.product', $product->item_type);
        $this->assertSame('COF-1KG', $product->item_sku);
        $this->assertSame('kg', $product->uom_code);
        $this->assertEqualsWithDelta(2.0, (float) $product->quantity, 0.000001);
        $this->assertSame(2_000_000, (int) $product->line_total_amount, 'line_total_amount is simply quantity x unit_price_amount.');

        $aliasProduct = $lines->firstWhere('item_id', 502);
        $this->assertNotNull($aliasProduct);
        $this->assertSame('shop.product', $aliasProduct->item_type);

        $service = $lines->firstWhere('item_type', 'custom.service');
        $this->assertNotNull($service);
        $this->assertNull($service->item_id);
        $this->assertSame('Installation service', $service->item_name);
        $this->assertSame(200_000, (int) $service->unit_price_amount);

        $text = $lines->firstWhere('item_type', 'custom.text');
        $this->assertNotNull($text);
        $this->assertEqualsWithDelta(1.0, (float) $text->quantity, 0.000001);
        $this->assertSame('Special packaging', $text->item_name);
        $this->assertSame(50_000, (int) $text->unit_price_amount);

        $custom = $lines->firstWhere('item_type', 'gift_card');
        $this->assertNotNull($custom);
        $this->assertSame(42, $custom->item_id);
        $this->assertSame('Gift card', $custom->item_name);
    }

    public function test_cart_line_supports_expiry_date_for_purchase_receiving(): void
    {
        $userId = 5;

        Commerce::cart()
            ->forUser($userId)
            ->addLine(
                itemType: 'shop.product',
                name: 'Milk 1L',
                quantity: 10,
                unitPrice: 30_000,
                itemId: 900,
                uomCode: 'ea',
                expiresAt: '2027-01-15',
            );

        $line = Commerce::cart()->forUser($userId)->items()->first();

        $this->assertSame('ea', $line->uom_code);
        $this->assertNotNull($line->expires_at);
        $this->assertSame('2027-01-15', $line->expires_at->toDateString());
    }

    public function test_cart_dimension_shortcuts_write_document_dimensions_rows_per_line(): void
    {
        $userId = 9;

        Commerce::cart()
            ->forUser($userId)
            ->salesUnitId(77)
            ->warehouseId(88)
            ->addDimension('channel_id', 5)
            ->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);

        $line = Commerce::cart()->forUser($userId)->items()->first();

        $this->assertSame(77, $line->dimensionValue('sales_unit_id'));
        $this->assertSame(88, $line->dimensionValue('warehouse_id'));
        $this->assertSame(5, $line->dimensionValue('channel_id'));
    }

    public function test_checkout_finalize_records_adjustments_dimensions_and_mandatory_invoice(): void
    {
        $userId = 2;
        $salesUnitId = 77;
        $warehouseId = 88;

        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 3, unitPrice: 100_000, itemId: 1);

        $result = Commerce::checkout()
            ->forUser($userId)
            ->salesUnitId($salesUnitId)
            ->warehouseId($warehouseId)
            ->addDimension('channel_id', 5)
            ->shippingAmount(20_000)
            ->taxAmount(9_000)
            ->discountAmount(10_000)
            ->addAdjustment('rounding', 1, sign: 1)
            ->finalize();

        $order = $result->order;
        $invoice = $result->invoice;

        // subtotal 300,000 + shipping 20,000 + tax 9,000 - discount 10,000 + rounding 1
        $this->assertSame(300_000, (int) $order->subtotal_amount);
        $this->assertSame(20_000, $order->shippingAmount());
        $this->assertSame(9_000, $order->taxAmount());
        $this->assertSame(10_000, $order->discountAmount());
        $this->assertSame(319_001, (int) $order->total_amount);
        $this->assertSame($salesUnitId, $order->sales_unit_id);
        $this->assertSame($warehouseId, $order->warehouse_id);

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame((int) $order->total_amount, (int) $invoice->amount);
        $this->assertSame($salesUnitId, $invoice->sales_unit_id);
        $this->assertSame($warehouseId, $invoice->warehouse_id);

        // Adjustment ledger rows exist even for the zero-touching shortcuts (none are zero here, but all 4 exist).
        $adjustments = $order->adjustments->keyBy('key');
        $this->assertCount(4, $adjustments);
        $this->assertSame(1, $adjustments['shipping']->sign);
        $this->assertSame(20_000, (int) $adjustments['shipping']->amount);
        $this->assertSame(-1, $adjustments['discount']->sign);
        $this->assertSame(10_000, (int) $adjustments['discount']->amount);
        $this->assertSame(1, (int) $adjustments['rounding']->amount);

        // Generic reporting dimensions.
        $dimensions = $order->dimensions->keyBy('key');
        $this->assertSame($salesUnitId, (int) $dimensions['sales_unit_id']->value_int);
        $this->assertSame($warehouseId, (int) $dimensions['warehouse_id']->value_int);
        $this->assertSame(5, (int) $dimensions['channel_id']->value_int);
    }

    public function test_checkout_finalize_stores_zero_adjustments_when_not_set(): void
    {
        $userId = 3;

        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);

        $result = Commerce::checkout()->forUser($userId)->finalize();

        $order = $result->order->fresh();

        $this->assertSame(0, $order->shippingAmount());
        $this->assertSame(0, $order->taxAmount());
        $this->assertSame(0, $order->discountAmount());
        $this->assertSame(100_000, (int) $order->total_amount);

        // Documented convention: shipping/tax/discount are always recorded, even at 0.
        $this->assertSame(3, $order->adjustments()->count());
    }

    public function test_invoices_issue_standalone_creates_an_invoice_with_no_order(): void
    {
        $invoice = Commerce::invoices()->issueStandalone(
            amount: 500_000,
            userId: 42,
            branchId: 3,
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertNull($invoice->order_id);
        $this->assertSame(500_000, (int) $invoice->amount);
        $this->assertSame(42, $invoice->user_id);
    }

    public function test_invoices_issue_standalone_records_optional_adjustments_and_dimensions(): void
    {
        $invoice = Commerce::invoices()
            ->salesUnitId(9)
            ->taxAmount(5_000)
            ->addAdjustment('coupon', 2_000, sign: -1)
            ->issueStandalone(amount: 100_000, userId: 42);

        $this->assertSame(9, $invoice->sales_unit_id);
        $this->assertSame(9, (int) $invoice->dimensions->firstWhere('key', 'sales_unit_id')->value_int);

        $adjustments = $invoice->adjustments->keyBy('key');
        $this->assertSame(5_000, (int) $adjustments['tax']->amount);
        $this->assertSame(-1, $adjustments['coupon']->sign);
        // amount() is authoritative and is never recomputed from adjustments.
        $this->assertSame(100_000, (int) $invoice->amount);
    }

    public function test_payment_initiate_dispatches_payment_initiated_event(): void
    {
        Event::fake();

        $userId = 6;
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: 1, unitPrice: 100_000, itemId: 1);
        $result = Commerce::checkout()->forUser($userId)->finalize();

        $payment = Commerce::payments()->forInvoice($result->invoice)->amount((int) $result->invoice->amount)->initiate();

        $this->assertSame(PaymentStatusEnum::PENDING, $payment->status);

        Event::assertDispatched(PaymentInitiated::class, function (PaymentInitiated $event) use ($result, $payment): bool {
            return (string) $event->invoiceId === (string) $result->invoice->id
                && (string) $event->paymentId === (string) $payment->id
                && (float) $event->amount === (float) $result->invoice->amount;
        });

        $confirmed = Commerce::payments()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-GENERIC');

        $this->assertSame(PaymentStatusEnum::PAID, $confirmed->status);
    }

    public function test_return_cannot_exceed_originally_sold_quantity(): void
    {
        [$order, $line] = $this->placeOrderWithOneLine(quantity: 2);

        $this->expectException(ReturnQuantityExceedsAvailable::class);

        Commerce::returns()
            ->forOrder($order)
            ->addLine(orderLineId: $line->id, quantity: 3)
            ->finalizeRefund();
    }

    public function test_return_requires_at_least_one_line(): void
    {
        [$order] = $this->placeOrderWithOneLine(quantity: 2);

        $this->expectException(CannotReturnWithoutLines::class);

        Commerce::returns()->forOrder($order)->finalizeRefund();
    }

    public function test_return_by_quantity_records_normalized_reason_and_refunds_to_wallet(): void
    {
        Event::fake();
        $this->seed(CommerceSeeder::class);

        $userId = 7;
        $branchId = 3;
        [$order, $line] = $this->placeOrderWithOneLine(quantity: 2, userId: $userId, unitPrice: 1_000_000);

        $paymentMethod = PaymentMethod::query()->create(['provider' => 'zarinpal', 'published' => true]);
        $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();
        $payment = Commerce::payments()
            ->forInvoice($invoice)
            ->forOrder($order)
            ->methodId($paymentMethod->id)
            ->amount((int) $invoice->amount)
            ->initiate();
        Commerce::payments()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-RETURN');

        $damagedReasonId = ReturnReason::query()->where('code', 'damaged')->value('id');
        $this->assertNotNull($damagedReasonId, 'CommerceSeeder must seed a "damaged" return reason.');

        $orderReturn = Commerce::returns()
            ->forOrder($order)
            ->idempotencyKey('return:order:'.$order->id.':v1')
            ->addLine(orderLineId: $line->id, quantity: 1, returnReasonId: $damagedReasonId, reasonNote: 'Box arrived crushed')
            ->finalizeRefundToWallet(userId: $userId, branchId: $branchId);

        $this->assertInstanceOf(OrderReturn::class, $orderReturn);
        $this->assertSame(1_000_000, (int) $orderReturn->total_amount);

        $returnLines = OrderReturnLine::query()->where('order_return_id', $orderReturn->id)->get();
        $this->assertCount(1, $returnLines);
        $this->assertSame($line->id, $returnLines->first()->order_line_id);
        $this->assertEqualsWithDelta(1.0, (float) $returnLines->first()->quantity, 0.000001);
        $this->assertSame(1_000_000, (int) $returnLines->first()->amount);
        $this->assertSame($damagedReasonId, $returnLines->first()->return_reason_id);
        $this->assertSame('Box arrived crushed', $returnLines->first()->reason_note);
        $this->assertSame('damaged', $returnLines->first()->returnReason->code);

        $walletTransaction = WalletTransaction::query()
            ->where('transactionable_type', $orderReturn->getMorphClass())
            ->where('transactionable_id', $orderReturn->id)
            ->first();
        $this->assertNotNull($walletTransaction, 'Return must credit a WalletTransaction when finalizeRefundToWallet() is used.');
        $this->assertSame(1_000_000, (int) $walletTransaction->amount);

        // Only half the total was returned -> order stays PAID, not REFUNDED.
        $order->refresh();
        $this->assertSame(FinancialStatusEnum::PAID, $order->financial_status);

        Event::assertDispatched(ReturnCreated::class, function (ReturnCreated $event) use ($order, $orderReturn): bool {
            return (string) $event->orderId === (string) $order->id
                && (string) $event->orderReturnId === (string) $orderReturn->id
                && (float) $event->totalAmount === 1_000_000.0
                && count($event->lines) === 1
                && (float) $event->lines[0]['amount'] === 1_000_000.0;
        });
    }

    public function test_return_to_wallet_defaults_branch_id_to_zero_for_global_wallet(): void
    {
        $userId = 12;
        [$order, $line] = $this->placeOrderWithOneLine(quantity: 1, userId: $userId, unitPrice: 500_000);

        Commerce::returns()
            ->forOrder($order)
            ->addLine(orderLineId: $line->id, quantity: 1)
            ->finalizeRefundToWallet(userId: $userId);

        $walletTransaction = WalletTransaction::query()->latest('id')->first();
        $this->assertNotNull($walletTransaction);
        $wallet = $walletTransaction->wallet;
        $this->assertSame(0, (int) $wallet->branch_id, 'Omitting branchId() must default to the global (0) wallet convention.');
    }

    public function test_return_full_quantity_flips_order_and_payments_to_refunded(): void
    {
        $userId = 8;
        $branchId = 3;
        [$order, $line] = $this->placeOrderWithOneLine(quantity: 2, userId: $userId, unitPrice: 1_000_000);

        $paymentMethod = PaymentMethod::query()->create(['provider' => 'zarinpal', 'published' => true]);
        $invoice = Invoice::query()->where('order_id', $order->id)->firstOrFail();
        $payment = Commerce::payments()
            ->forInvoice($invoice)
            ->forOrder($order)
            ->methodId($paymentMethod->id)
            ->amount((int) $invoice->amount)
            ->initiate();
        Commerce::payments()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-FULL');

        Commerce::returns()
            ->forOrder($order)
            ->addLine(orderLineId: $line->id, quantity: 2, reasonNote: 'Full return')
            ->finalizeRefundToWallet(userId: $userId, branchId: $branchId);

        $order->refresh();
        $this->assertSame(FinancialStatusEnum::REFUNDED, $order->financial_status);
        $this->assertSame(PaymentStatusEnum::REFUNDED, $payment->refresh()->status);
    }

    public function test_return_is_idempotent_for_retries_with_the_same_key(): void
    {
        [$order, $line] = $this->placeOrderWithOneLine(quantity: 2);

        $first = Commerce::returns()
            ->forOrder($order)
            ->idempotencyKey('return:retry')
            ->addLine(orderLineId: $line->id, quantity: 1)
            ->finalizeRefund();

        $second = Commerce::returns()
            ->forOrder($order)
            ->idempotencyKey('return:retry')
            ->addLine(orderLineId: $line->id, quantity: 1)
            ->finalizeRefund();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OrderReturn::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderReturnLine::query()->where('order_return_id', $first->id)->count());
    }

    public function test_return_throws_idempotency_conflict_for_a_different_order_with_the_same_key(): void
    {
        [$orderA, $lineA] = $this->placeOrderWithOneLine(quantity: 2, userId: 10);
        [$orderB, $lineB] = $this->placeOrderWithOneLine(quantity: 2, userId: 11);

        Commerce::returns()
            ->forOrder($orderA)
            ->idempotencyKey('return:shared')
            ->addLine(orderLineId: $lineA->id, quantity: 1)
            ->finalizeRefund();

        $this->expectException(IdempotencyConflict::class);

        Commerce::returns()
            ->forOrder($orderB)
            ->idempotencyKey('return:shared')
            ->addLine(orderLineId: $lineB->id, quantity: 1)
            ->finalizeRefund();
    }

    /**
     * @return array{0: Order, 1: OrderLine}
     */
    private function placeOrderWithOneLine(int $quantity, int $userId = 100, int $unitPrice = 500_000): array
    {
        Commerce::cart()->forUser($userId)->addLine(itemType: 'shop.product', name: 'Widget', quantity: $quantity, unitPrice: $unitPrice, itemId: 1);

        $result = Commerce::checkout()->forUser($userId)->finalize();
        $line = OrderLine::query()->where('order_id', $result->order->id)->first();

        return [$result->order, $line];
    }
}
