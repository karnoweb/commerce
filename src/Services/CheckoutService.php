<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Karnoweb\Commerce\Contracts\CommerceContextResolverContract;
use Karnoweb\Commerce\Contracts\OrderNumberGeneratorContract;
use Karnoweb\Commerce\DTOs\CheckoutResult;
use Karnoweb\Commerce\DTOs\CheckoutResultWithPayments;
use Karnoweb\Commerce\Enums\FinancialStatusEnum;
use Karnoweb\Commerce\Enums\OrderTypeEnum;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;
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
 * Cart source: OrderLine rows with order_id NULL for the given user.
 *
 * finalize() always:
 *  - moves the user's cart lines (OrderLine, order_id null) onto the order,
 *  - records shipping/tax/discount + any custom key as document_adjustments
 *    rows (a flexible +/- ledger against the order — not fixed columns),
 *  - writes sales_unit_id/warehouse_id (+ any addDimension() pairs) as
 *    document_dimensions rows for generic reporting, in addition to the
 *    orders.sales_unit_id/warehouse_id shortcut columns,
 *  - creates the order's mandatory Invoice (never leaves an order unbilled).
 *
 * finalizeWithPayments() does the same, then writes 1..n PENDING payment
 * records (no gateway call). Confirmation stays a separate step.
 */
class CheckoutService
{
    use ResolvesConfiguredModels;

    public function __construct(
        private readonly CartService $cartService,
        private readonly InvoiceService $invoiceService,
        private readonly OrderNumberGeneratorContract $orderNumberGenerator,
        private readonly PaymentService $paymentService,
        private readonly CommerceContextResolverContract $contextResolver,
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
            $branchId = $data['branch_id'] ?? $this->contextResolver->branchId();

            if ($idempotencyKey !== null) {
                $existing = $this->findOrderByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSameCheckoutPayload($existing, $data, $branchId, $idempotencyKey);

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

            $orderNumber = $data['order_number'] ?? $this->orderNumberGenerator->generate($branchId);

            /** @var Order $order */
            $order = $orderClass::create([
                'idempotency_key' => $idempotencyKey,
                'order_number' => $orderNumber,
                'user_id' => $userId,
                'branch_id' => $branchId,
                'sales_unit_id' => $data['sales_unit_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'type' => OrderTypeEnum::SALE,
                'financial_status' => FinancialStatusEnum::PENDING,
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

    /**
     * Same as finalize(), then create 1..n PENDING payment records against
     * the new invoice. No gateway is called — confirm() remains a separate
     * step. Retry-safe when the checkout idempotency key is set: derived
     * per-index payment keys reuse the same payment rows.
     *
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
     * @param  list<array{method_id?: int|string|null, type?: PaymentTypeEnum|string, amount: int|float, extra?: array<string, mixed>, idempotency_key?: string|null}>  $payments
     */
    public function finalizeWithPayments(array $data, array $payments): CheckoutResultWithPayments
    {
        return DB::transaction(function () use ($data, $payments): CheckoutResultWithPayments {
            $result = $this->finalize($data);
            $created = [];
            $checkoutKey = $data['idempotency_key'] ?? null;

            foreach ($payments as $index => $payment) {
                $paymentKey = $payment['idempotency_key'] ?? ($checkoutKey !== null ? $checkoutKey.':payment:'.$index : null);

                $created[] = $this->paymentService->initiate(
                    $result->invoice,
                    $result->order,
                    $payment['method_id'] ?? null,
                    $payment['type'] ?? PaymentTypeEnum::CASH,
                    $payment['amount'],
                    $paymentKey,
                    $payment['extra'] ?? [],
                );
            }

            return new CheckoutResultWithPayments($result->order, $result->invoice, $created);
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
    private function assertSameCheckoutPayload(Order $existing, array $data, int|string|null $branchId, string $idempotencyKey): void
    {
        $sameUser = (string) $existing->user_id === (string) $data['user_id'];
        $sameBranch = (string) ($existing->branch_id ?? '') === (string) ($branchId ?? '');

        if (! $sameUser || ! $sameBranch) {
            throw new IdempotencyConflict($idempotencyKey);
        }
    }
}
