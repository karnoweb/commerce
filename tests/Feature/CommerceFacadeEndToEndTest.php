<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;
use Karnoweb\Commerce\Events\InvoiceFullyPaid;
use Karnoweb\Commerce\Events\OrderCreated;
use Karnoweb\Commerce\Events\OrderPaid;
use Karnoweb\Commerce\Events\RefundCreated;
use Karnoweb\Commerce\Exceptions\CannotCheckoutEmptyCart;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Facades\Commerce;
use Karnoweb\Commerce\Models\Discount;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderItem;
use Karnoweb\Commerce\Models\OrderReturn;
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
 * End-to-end coverage of the canonical Facade flow from section 2 of the
 * commerce package mission: cart -> checkout -> invoice -> payment ->
 * refund. Runs against a standalone sqlite install — no host models.
 */
final class CommerceFacadeEndToEndTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'commerce.models.user' => FakeUser::class,
            'commerce.models.order' => Order::class,
            'commerce.models.order_item' => OrderItem::class,
            'commerce.models.order_return' => OrderReturn::class,
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

    public function test_full_cart_to_refund_flow_via_facade(): void
    {
        Event::fake();

        $userId = 9001;
        $branchId = 3;

        $itemSnapshot = [
            'title' => 'Coffee Beans 1kg',
            'sku' => 'COF-1KG',
            'price_source' => 'user_group',
            'campaign_id' => null,
        ];

        Commerce::cart()
            ->forUser($userId)
            ->branchId($branchId)
            ->addItem(
                productId: 501,
                quantity: 2,
                unitPrice: 1_000_000,
                extra: $itemSnapshot,
            );

        $cartItems = Commerce::cart()->forUser($userId)->items();
        $this->assertCount(1, $cartItems);
        $this->assertSame('Coffee Beans 1kg', $cartItems->first()->extra_attributes['title']);

        $order = Commerce::checkout()
            ->forUser($userId)
            ->branchId($branchId)
            ->shippingAmount(50_000)
            ->idempotencyKey('checkout:user:9001:cart:active')
            ->place();

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(OrderStatusEnum::PENDING, $order->status);
        $this->assertSame($branchId, $order->branch_id);
        $this->assertSame(2_050_000.0, (float) $order->total); // 2 * 1,000,000 + 50,000 shipping

        Event::assertDispatched(OrderCreated::class, function (OrderCreated $event) use ($order): bool {
            return (string) $event->orderId === (string) $order->id;
        });

        // Cart items are attached to the order, not left dangling.
        $this->assertSame(0, OrderItem::query()->carts()->where('user_id', $userId)->count());
        $this->assertSame(1, OrderItem::query()->where('order_id', $order->id)->count());

        $invoice = Commerce::checkout()->forOrder($order)->createInvoice(invoiceNumber: 'INV-1');

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame('INV-1', $invoice->invoice_number);
        $this->assertSame($order->id, $invoice->order_id);

        $paymentMethod = PaymentMethod::query()->create(['provider' => 'zarinpal', 'published' => true]);

        $payment = Commerce::payment()
            ->forOrder($order)
            ->forInvoice($invoice)
            ->methodId($paymentMethod->id)
            ->type(PaymentTypeEnum::ONLINE)
            ->amount((int) $order->total)
            ->idempotencyKey('pay:order:'.$order->id.':attempt:1')
            ->initiate();

        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertSame(PaymentStatusEnum::PENDING, $payment->status);

        $confirmed = Commerce::payment()->confirm(
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
        $this->assertSame(1, Transaction::query()->where('order_id', $order->id)->where('tracking_code', 'TRK-777')->count());

        Event::assertDispatched(OrderPaid::class, function (OrderPaid $event) use ($order): bool {
            return (string) $event->orderId === (string) $order->id;
        });
        Event::assertDispatched(InvoiceFullyPaid::class, function (InvoiceFullyPaid $event) use ($invoice): bool {
            return (string) $event->invoiceId === (string) $invoice->id;
        });

        // Partial refund to wallet.
        $orderReturn = Commerce::refund()
            ->forOrder($order)
            ->amount(1_000_000)
            ->reason('customer_return')
            ->toWallet(userId: $userId, branchId: $branchId)
            ->idempotencyKey('refund:order:'.$order->id.':amount:1000000')
            ->process();

        $this->assertInstanceOf(OrderReturn::class, $orderReturn);
        $this->assertSame($order->id, $orderReturn->order_id);
        $this->assertSame(1_000_000.0, (float) $orderReturn->amount);

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

    public function test_place_throws_when_cart_is_empty(): void
    {
        $this->expectException(CannotCheckoutEmptyCart::class);

        Commerce::checkout()->forUser(4242)->place();
    }

    public function test_place_is_idempotent_for_retries_with_the_same_key(): void
    {
        $userId = 1;

        Commerce::cart()->forUser($userId)->addItem(productId: 1, quantity: 1, unitPrice: 100_000);

        $first = Commerce::checkout()->forUser($userId)->idempotencyKey('checkout:retry')->place();
        $second = Commerce::checkout()->forUser($userId)->idempotencyKey('checkout:retry')->place();

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, OrderItem::query()->where('order_id', $first->id)->count());
        $this->assertSame(1, Order::query()->count());
    }

    public function test_place_throws_idempotency_conflict_for_a_different_user_with_the_same_key(): void
    {
        Commerce::cart()->forUser(1)->addItem(productId: 1, quantity: 1, unitPrice: 100_000);
        Commerce::checkout()->forUser(1)->idempotencyKey('checkout:shared')->place();

        Commerce::cart()->forUser(2)->addItem(productId: 1, quantity: 1, unitPrice: 100_000);

        $this->expectException(IdempotencyConflict::class);

        Commerce::checkout()->forUser(2)->idempotencyKey('checkout:shared')->place();
    }

    public function test_confirm_is_idempotent_for_retries_with_the_same_tracking_code(): void
    {
        $userId = 1;
        Commerce::cart()->forUser($userId)->addItem(productId: 1, quantity: 1, unitPrice: 100_000);
        $order = Commerce::checkout()->forUser($userId)->place();

        $payment = Commerce::payment()->forOrder($order)->amount((int) $order->total)->initiate();

        $first = Commerce::payment()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-SAME');
        $second = Commerce::payment()->confirm($payment, gateway: 'zarinpal', trackingCode: 'TRK-SAME');

        $this->assertSame(PaymentStatusEnum::PAID, $first->status);
        $this->assertSame(PaymentStatusEnum::PAID, $second->status);
        $this->assertSame(1, Transaction::query()->where('order_id', $order->id)->count());
    }
}
