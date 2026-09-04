<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Karnoweb\Commerce\DTOs\CheckoutResult;
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
 * no HTTP access, no shop/host model dependency. Every order line is a
 * generic item_type/item_id/item_name reference — there is no product_id.
 *
 * finalize() always:
 *  - moves the user's cart lines (OrderLine, order_id null) onto the order,
 *  - records shipping/tax/discount + any custom key as document_adjustments
 *    rows (a flexible +/- ledger against the order — not fixed columns),
 *  - writes sales_unit_id/warehouse_id (+ any addDimension() pairs) as
 *    document_dimensions rows for generic reporting, in addition to the
 *    orders.sales_unit_id/warehouse_id shortcut columns,
 *  - creates the order's mandatory Invoice (never leaves an order unbilled).
 */
class CheckoutService
{
    use ResolvesConfiguredModels;

    public function __construct(
        private readonly CartService $cartService,
        private readonly InvoiceService $invoiceService,
    ) {}

    /**
     * @param  array{
     *     user_id: int|string,
     *     branch_id?: int|string|null,
     *     sales_unit_id?: int|string|null,
     *     warehouse_id?: int|string|null,
     *     order_number?: string|null,
     *     idempotency_key?: string|null,
     *     currency?: string|null,
     *     note?: string|null,
     *     invoice_number?: string|null,
     *     adjustments?: list<array{key: string, sign?: int, amount: int|float, payload?: array|null}>,
     *     dimensions?: array<string, mixed>,
     * } $data
     *
     * @throws CannotCheckoutEmptyCart
     * @throws IdempotencyConflict
     */
    public function finalize(array $data): CheckoutResult
    {
        return DB::transaction(function () use ($data): CheckoutResult {
            $userId = $data['user_id'];
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey !== null) {
                $existing = $this->findOrderByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSameCheckoutPayload($existing, $data, $idempotencyKey);

                    return new CheckoutResult($existing, $this->latestInvoiceFor($existing));
                }
            }

            $lines = $this->cartService->items($userId);

            if ($lines->isEmpty()) {
                throw new CannotCheckoutEmptyCart($userId);
            }

            $subtotalAmount = (int) $lines->sum('line_total_amount');

            /** @var list<array{key: string, sign: int, amount: int, payload: array|null}> $adjustments */
            $adjustments = [];

            foreach ($data['adjustments'] ?? [] as $adjustment) {
                $adjustments[] = [
                    'key' => $adjustment['key'],
                    'sign' => $adjustment['sign'] ?? 1,
                    'amount' => (int) round($adjustment['amount']),
                    'payload' => $adjustment['payload'] ?? null,
                ];
            }

            $adjustmentTotal = 0;

            foreach ($adjustments as $adjustment) {
                $adjustmentTotal += $adjustment['sign'] * $adjustment['amount'];
            }

            $totalAmount = $subtotalAmount + $adjustmentTotal;

            $orderClass = static::model('order');

            /** @var Order $order */
            $order = $orderClass::create([
                'idempotency_key' => $idempotencyKey,
                'order_number' => $data['order_number'] ?? $this->generateOrderNumber(),
                'user_id' => $userId,
                'branch_id' => $data['branch_id'] ?? null,
                'sales_unit_id' => $data['sales_unit_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'type' => OrderTypeEnum::SALE,
                'status' => OrderStatusEnum::PENDING,
                'subtotal_amount' => $subtotalAmount,
                'total_amount' => $totalAmount,
                'currency' => $data['currency'] ?? null,
                'note' => $data['note'] ?? null,
            ]);

            foreach ($lines as $line) {
                $line->update(['order_id' => $order->id]);
            }

            foreach ($adjustments as $adjustment) {
                $order->adjustments()->create($adjustment);
            }

            foreach ($data['dimensions'] ?? [] as $key => $value) {
                $order->addDimension($key, $value);
            }

            $invoice = $this->invoiceService->createForOrder($order, $data['invoice_number'] ?? null);

            CommerceEventDispatcher::dispatch(new OrderCreated(
                orderId: $order->id,
                userId: $order->user_id,
            ));

            return new CheckoutResult($order, $invoice);
        });
    }

    /** @deprecated Alias for finalize(). Kept for backward compatibility. */
    public function place(array $data): CheckoutResult
    {
        return $this->finalize($data);
    }

    /**
     * Attach an *additional* invoice to an order — finalize() already
     * creates the mandatory one. Delegates to InvoiceService so
     * CheckoutBuilder::createInvoice() keeps working unchanged.
     */
    public function createInvoice(Order $order, ?string $invoiceNumber = null): Invoice
    {
        return $this->invoiceService->createForOrder($order, $invoiceNumber);
    }

    private function findOrderByIdempotencyKey(string $key): ?Order
    {
        $orderClass = static::model('order');

        return $orderClass::query()->where('idempotency_key', $key)->first();
    }

    private function latestInvoiceFor(Order $order): Invoice
    {
        $invoiceClass = static::model('invoice');

        return $invoiceClass::query()->where('order_id', $order->id)->latest('id')->firstOrFail();
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
}
