<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\OrderTypeEnum;
use Karnoweb\Commerce\Events\OrderCreated;
use Karnoweb\Commerce\Exceptions\CannotCheckoutEmptyCart;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Canonical order/invoice creation. Package-safe: no session/auth helpers,
 * no HTTP access, no shop/host model dependency. Pricing/discount evaluation stays in the
 * host; this service only persists the cart snapshot and totals it is given.
 */
class CheckoutService
{
    use ResolvesConfiguredModels;

    public function __construct(private readonly CartService $cartService) {}

    /**
     * Create an Order from the user's cart items (OrderItem rows with a
     * null order_id), attach them, and dispatch OrderCreated after commit.
     *
     * @param array{
     *     user_id: int|string,
     *     branch_id?: int|string|null,
     *     shipping_amount?: int|float,
     *     tax_amount?: int|float,
     *     discount_amount?: int|float,
     *     order_number?: string|null,
     *     idempotency_key?: string|null,
     * } $data
     *
     * @throws CannotCheckoutEmptyCart
     * @throws IdempotencyConflict
     */
    public function placeFromCart(array $data): Order
    {
        return DB::transaction(function () use ($data): Order {
            $userId = $data['user_id'];
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey !== null) {
                $existing = $this->findOrderByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSameCheckoutPayload($existing, $data, $idempotencyKey);

                    return $existing;
                }
            }

            $items = $this->cartService->items($userId);

            if ($items->isEmpty()) {
                throw new CannotCheckoutEmptyCart($userId);
            }

            $subtotal = 0.0;
            $itemDiscount = 0.0;
            $itemTax = 0.0;

            foreach ($items as $item) {
                $subtotal += (float) $item->sale_price * (int) $item->quantity;
                $itemDiscount += (float) $item->discount_amount;
                $itemTax += (float) $item->tax_amount;
            }

            $shippingAmount = (float) ($data['shipping_amount'] ?? 0);
            $discountAmount = $itemDiscount + (float) ($data['discount_amount'] ?? 0);
            $taxAmount = $itemTax + (float) ($data['tax_amount'] ?? 0);
            $total = $subtotal - $discountAmount + $taxAmount + $shippingAmount;

            $orderClass = static::model('order');

            $order = $orderClass::create([
                'order_number' => $data['order_number'] ?? $this->generateOrderNumber(),
                'idempotency_key' => $idempotencyKey,
                'user_id' => $userId,
                'branch_id' => $data['branch_id'] ?? null,
                'status' => OrderStatusEnum::PENDING,
                'type' => OrderTypeEnum::SALE,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'shipping_amount' => $shippingAmount,
                'total' => $total,
                'order_date' => now()->toDateString(),
            ]);

            foreach ($items as $item) {
                $item->update(['order_id' => $order->id]);
            }

            CommerceEventDispatcher::dispatch(new OrderCreated(
                orderId: $order->id,
                userId: $order->user_id,
            ));

            return $order;
        });
    }

    /**
     * Optional package-safe invoice for an order. Does not talk to the
     * host accounting bridge — that stays a host coordinator concern.
     */
    public function createInvoice(Order $order, ?string $invoiceNumber = null): Invoice
    {
        $invoiceClass = static::model('invoice');

        return $invoiceClass::create([
            'invoice_number' => $invoiceNumber ?? $this->generateInvoiceNumber(),
            'branch_id' => $order->branch_id ?? 1,
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'amount' => $order->total,
            'tax_amount' => $order->tax_amount,
            'discount_amount' => $order->discount_amount,
            'invoice_date' => now()->toDateString(),
            'status' => 'issued',
        ]);
    }

    private function findOrderByIdempotencyKey(string $key): ?Order
    {
        $orderClass = static::model('order');

        return $orderClass::query()->where('idempotency_key', $key)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertSameCheckoutPayload(Order $existing, array $data, string $idempotencyKey): void
    {
        $sameUser = (string) $existing->user_id === (string) $data['user_id'];
        $sameBranch = (string) ($existing->branch_id ?? '') === (string) ($data['branch_id'] ?? '');

        if (! $sameUser || ! $sameBranch) {
            throw new IdempotencyConflict($idempotencyKey);
        }
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    private function generateInvoiceNumber(): string
    {
        return 'INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}
