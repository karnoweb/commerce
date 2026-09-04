<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Karnoweb\Commerce\Contracts\DiscountCalculatorContract;
use Karnoweb\Commerce\Contracts\TaxCalculatorContract;
use Karnoweb\Commerce\Enums\LineItemTypeEnum;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Events\PaymentInitiated;
use Karnoweb\Commerce\Events\ReturnCreated;
use Karnoweb\Commerce\Exceptions\CannotReturnWithoutItems;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Exceptions\ReturnQuantityExceedsAvailable;
use Karnoweb\Commerce\Facades\Commerce;
use Karnoweb\Commerce\Models\Discount;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderItem;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Models\OrderReturnItem;
use Karnoweb\Commerce\Models\OrderTotal;
use Karnoweb\Commerce\Models\Payment;
use Karnoweb\Commerce\Models\PaymentMethod;
use Karnoweb\Commerce\Models\ShippingMethod;
use Karnoweb\Commerce\Models\Transaction;
use Karnoweb\Commerce\Models\Wallet;
use Karnoweb\Commerce\Models\WalletTransaction;
use Karnoweb\Commerce\Tests\Fixtures\FakeUser;
use Karnoweb\Commerce\Tests\TestCase;

/**
 * Coverage for generic line items (product/service/text/custom) and
 * quantity-based returns — the "most businesses" feature set layered on
 * top of the Facade-centric flow from CommerceFacadeEndToEndTest. Runs
 * against a standalone sqlite install — no host models.
 */
final class CommerceGenericLineItemsAndReturnsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'commerce.models.user' => FakeUser::class,
            'commerce.models.order' => Order::class,
            'commerce.models.order_item' => OrderItem::class,
            'commerce.models.order_return' => OrderReturn::class,
            'commerce.models.order_return_item' => OrderReturnItem::class,
            'commerce.models.order_total' => OrderTotal::class,
            'commerce.models.invoice' => Invoice::class,
            'commerce.models.payment' => Payment::class,
            'commerce.models.transaction' => Transaction::class,
            'commerce.models.discount' => Discount::class,
            'commerce.models.wallet' => Wallet::class,
            'commerce.models.wallet_transaction' => WalletTransaction::class,
            'commerce.models.shipping_method' => ShippingMethod::class,
            'commerce.models.payment_method' => PaymentMethod::class,
            'commerce.models.product' => FakeUser::class,
            'commerce.models.campaign' => FakeUser::class,
            'commerce.models.address' => FakeUser::class,
        ]);

        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_cart_supports_product_service_text_and_custom_lines(): void
    {
        $userId = 1;

        Commerce::cart()
            ->forUser($userId)
            ->addProductItem(productId: 501, quantity: 2, unitPrice: 1_000_000, extra: ['sku' => 'COF-1KG', 'title' => 'Coffee Beans 1kg'])
            ->addServiceItem(title: 'Installation service', quantity: 1, unitPrice: 200_000)
            ->addTextItem(description: 'Special packaging', amount: 50_000)
            ->addCustomItem(itemableType: 'gift_card', itemableId: 42, title: 'Gift card', quantity: 1, unitPrice: 100_000);

        $items = Commerce::cart()->forUser($userId)->items();

        $this->assertCount(4, $items);

        $product = $items->firstWhere('item_type', LineItemTypeEnum::PRODUCT);
        $this->assertNotNull($product);
        $this->assertSame(501, $product->product_id);
        $this->assertSame(2, $product->quantity);
        $this->assertSame('Coffee Beans 1kg', $product->extra_attributes['title']);

        $service = $items->firstWhere('item_type', LineItemTypeEnum::SERVICE);
        $this->assertNotNull($service);
        $this->assertNull($service->product_id);
        $this->assertSame('Installation service', $service->title);
        $this->assertSame(200_000.0, (float) $service->sale_price);

        $text = $items->firstWhere('item_type', LineItemTypeEnum::TEXT);
        $this->assertNotNull($text);
        $this->assertSame(1, $text->quantity);
        $this->assertSame('Special packaging', $text->title);
        $this->assertSame(50_000.0, (float) $text->sale_price);

        $custom = $items->firstWhere('item_type', LineItemTypeEnum::CUSTOM);
        $this->assertNotNull($custom);
        $this->assertNull($custom->product_id);
        $this->assertSame('gift_card', $custom->itemable_type);
        $this->assertSame(42, $custom->itemable_id);
        $this->assertSame('Gift card', $custom->title);
    }

    public function test_checkout_place_is_idempotent_with_mixed_generic_lines(): void
    {
        $userId = 2;

        Commerce::cart()
            ->forUser($userId)
            ->addProductItem(productId: 501, quantity: 2, unitPrice: 1_000_000)
            ->addServiceItem(title: 'Installation', quantity: 1, unitPrice: 200_000)
            ->addTextItem(description: 'Packaging', amount: 50_000);

        $first = Commerce::checkout()->forUser($userId)->idempotencyKey('checkout:generic:retry')->place();
        $second = Commerce::checkout()->forUser($userId)->idempotencyKey('checkout:generic:retry')->place();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(2_250_000.0, (float) $first->total); // 2*1,000,000 + 200,000 + 50,000
        $this->assertSame(3, OrderItem::query()->where('order_id', $first->id)->count());
    }

    public function test_checkout_uses_null_tax_and_discount_calculators_by_default_when_not_specified(): void
    {
        $userId = 3;

        Commerce::cart()->forUser($userId)->addProductItem(productId: 1, quantity: 1, unitPrice: 100_000);

        // Neither taxAmount() nor discountAmount() called -> falls back to
        // the bound (no-op) calculators, exactly like an explicit 0 would.
        $order = Commerce::checkout()->forUser($userId)->place();

        $this->assertSame(0.0, (float) $order->tax_amount);
        $this->assertSame(0.0, (float) $order->discount_amount);
        $this->assertSame(100_000.0, (float) $order->total);
    }

    public function test_checkout_totals_can_be_overridden_by_host_calculator_bindings(): void
    {
        $this->app->bind(TaxCalculatorContract::class, function () {
            return new class implements TaxCalculatorContract
            {
                public function calculate(Collection $items, array $context): int|float
                {
                    return 12_345;
                }
            };
        });

        $this->app->bind(DiscountCalculatorContract::class, function () {
            return new class implements DiscountCalculatorContract
            {
                public function calculate(Collection $items, array $context): int|float
                {
                    return 1_000;
                }
            };
        });

        $userId = 4;

        Commerce::cart()->forUser($userId)->addProductItem(productId: 1, quantity: 1, unitPrice: 100_000);

        $order = Commerce::checkout()->forUser($userId)->place();

        $this->assertSame(12_345.0, (float) $order->tax_amount);
        $this->assertSame(1_000.0, (float) $order->discount_amount);
        $this->assertSame(100_000.0 - 1_000 + 12_345, (float) $order->total);
    }

    public function test_checkout_explicit_zero_wins_over_calculator_bindings(): void
    {
        $this->app->bind(TaxCalculatorContract::class, function () {
            return new class implements TaxCalculatorContract
            {
                public function calculate(Collection $items, array $context): int|float
                {
                    return 99_999;
                }
            };
        });

        $userId = 5;

        Commerce::cart()->forUser($userId)->addProductItem(productId: 1, quantity: 1, unitPrice: 100_000);

        $order = Commerce::checkout()->forUser($userId)->taxAmount(0)->place();

        $this->assertSame(0.0, (float) $order->tax_amount);
    }

    public function test_payment_initiate_dispatches_payment_initiated_event(): void
    {
        Event::fake();

        $userId = 6;
        Commerce::cart()->forUser($userId)->addProductItem(productId: 1, quantity: 1, unitPrice: 100_000);
        $order = Commerce::checkout()->forUser($userId)->place();

        $payment = Commerce::payment()->forOrder($order)->amount((int) $order->total)->initiate();

        $this->assertSame(PaymentStatusEnum::PENDING, $payment->status);

        Event::assertDispatched(PaymentInitiated::class, function (PaymentInitiated $event) use ($order, $payment): bool {
            return (string) $event->orderId === (string) $order->id
                && (string) $event->paymentId === (string) $payment->id
                && (float) $event->amount === (float) $order->total;
        });

        $confirmed = Commerce::payment()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-GENERIC');

        $this->assertSame(PaymentStatusEnum::PAID, $confirmed->status);
    }

    public function test_return_cannot_exceed_originally_sold_quantity(): void
    {
        [$order, $productItem] = $this->placeOrderWithOneProductLine(quantity: 2);

        $this->expectException(ReturnQuantityExceedsAvailable::class);

        Commerce::returns()
            ->forOrder($order)
            ->addItem(orderItemId: $productItem->id, quantity: 3)
            ->finalizeAndRefund();
    }

    public function test_return_requires_at_least_one_item(): void
    {
        [$order] = $this->placeOrderWithOneProductLine(quantity: 2);

        $this->expectException(CannotReturnWithoutItems::class);

        Commerce::returns()->forOrder($order)->finalizeAndRefund();
    }

    public function test_return_by_quantity_creates_order_return_and_items_and_refunds_to_wallet(): void
    {
        Event::fake();

        $userId = 7;
        $branchId = 3;
        [$order, $productItem] = $this->placeOrderWithOneProductLine(quantity: 2, userId: $userId, unitPrice: 1_000_000);

        $paymentMethod = PaymentMethod::query()->create(['provider' => 'zarinpal', 'published' => true]);
        $payment = Commerce::payment()
            ->forOrder($order)
            ->methodId($paymentMethod->id)
            ->amount((int) $order->total)
            ->initiate();
        Commerce::payment()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-RETURN');

        $orderReturn = Commerce::returns()
            ->forOrder($order)
            ->idempotencyKey('return:order:'.$order->id.':v1')
            ->addItem(orderItemId: $productItem->id, quantity: 1, reason: 'Customer return')
            ->finalizeAndRefundToWallet(userId: $userId, branchId: $branchId);

        $this->assertInstanceOf(OrderReturn::class, $orderReturn);
        $this->assertSame(1_000_000.0, (float) $orderReturn->amount);

        $returnItems = OrderReturnItem::query()->where('order_return_id', $orderReturn->id)->get();
        $this->assertCount(1, $returnItems);
        $this->assertSame($productItem->id, $returnItems->first()->order_item_id);
        $this->assertSame(1, $returnItems->first()->quantity);
        $this->assertSame(1_000_000.0, (float) $returnItems->first()->amount);

        $walletTransaction = WalletTransaction::query()
            ->where('transactionable_type', $orderReturn->getMorphClass())
            ->where('transactionable_id', $orderReturn->id)
            ->first();
        $this->assertNotNull($walletTransaction, 'Return must credit a WalletTransaction when finalizeAndRefundToWallet() is used.');
        $this->assertSame(1_000_000, (int) $walletTransaction->amount);

        // Only half the total was returned -> order stays PAID, not REFUNDED.
        $order->refresh();
        $this->assertSame(OrderStatusEnum::PAID, $order->status);

        Event::assertDispatched(ReturnCreated::class, function (ReturnCreated $event) use ($order, $orderReturn): bool {
            return (string) $event->orderId === (string) $order->id
                && (string) $event->orderReturnId === (string) $orderReturn->id
                && (float) $event->totalAmount === 1_000_000.0
                && count($event->items) === 1
                && (float) $event->items[0]['amount'] === 1_000_000.0;
        });
    }

    public function test_return_full_quantity_flips_order_and_payments_to_refunded(): void
    {
        $userId = 8;
        $branchId = 3;
        [$order, $productItem] = $this->placeOrderWithOneProductLine(quantity: 2, userId: $userId, unitPrice: 1_000_000);

        $paymentMethod = PaymentMethod::query()->create(['provider' => 'zarinpal', 'published' => true]);
        $payment = Commerce::payment()
            ->forOrder($order)
            ->methodId($paymentMethod->id)
            ->amount((int) $order->total)
            ->initiate();
        Commerce::payment()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-FULL');

        Commerce::returns()
            ->forOrder($order)
            ->addItem(orderItemId: $productItem->id, quantity: 2, reason: 'Full return')
            ->finalizeAndRefundToWallet(userId: $userId, branchId: $branchId);

        $order->refresh();
        $this->assertSame(OrderStatusEnum::REFUNDED, $order->status);
        $this->assertSame(PaymentStatusEnum::REFUNDED, $payment->refresh()->status);
    }

    public function test_return_is_idempotent_for_retries_with_the_same_key(): void
    {
        [$order, $productItem] = $this->placeOrderWithOneProductLine(quantity: 2);

        $first = Commerce::returns()
            ->forOrder($order)
            ->idempotencyKey('return:retry')
            ->addItem(orderItemId: $productItem->id, quantity: 1)
            ->finalizeAndRefund();

        $second = Commerce::returns()
            ->forOrder($order)
            ->idempotencyKey('return:retry')
            ->addItem(orderItemId: $productItem->id, quantity: 1)
            ->finalizeAndRefund();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OrderReturn::query()->where('order_id', $order->id)->count());
        $this->assertSame(1, OrderReturnItem::query()->where('order_return_id', $first->id)->count());
    }

    public function test_return_throws_idempotency_conflict_for_a_different_order_with_the_same_key(): void
    {
        [$orderA, $itemA] = $this->placeOrderWithOneProductLine(quantity: 2, userId: 10);
        [$orderB, $itemB] = $this->placeOrderWithOneProductLine(quantity: 2, userId: 11);

        Commerce::returns()
            ->forOrder($orderA)
            ->idempotencyKey('return:shared')
            ->addItem(orderItemId: $itemA->id, quantity: 1)
            ->finalizeAndRefund();

        $this->expectException(IdempotencyConflict::class);

        Commerce::returns()
            ->forOrder($orderB)
            ->idempotencyKey('return:shared')
            ->addItem(orderItemId: $itemB->id, quantity: 1)
            ->finalizeAndRefund();
    }

    /**
     * @return array{0: Order, 1: OrderItem}
     */
    private function placeOrderWithOneProductLine(int $quantity, int $userId = 100, int $unitPrice = 500_000): array
    {
        Commerce::cart()->forUser($userId)->addProductItem(productId: 1, quantity: $quantity, unitPrice: $unitPrice);

        $order = Commerce::checkout()->forUser($userId)->place();
        $productItem = OrderItem::query()->where('order_id', $order->id)->first();

        return [$order, $productItem];
    }
}
