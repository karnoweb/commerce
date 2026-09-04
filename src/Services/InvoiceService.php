<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Karnoweb\Commerce\Contracts\InvoiceNumberGeneratorContract;
use Karnoweb\Commerce\Events\InvoiceIssued;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Invoice creation. An Order's invoice is mandatory — CheckoutService::
 * finalize() always calls createForOrder(). issueStandalone() supports the
 * order-less path (billing with no order at all). `amount` is always the
 * final total — there is no tax_amount/discount_amount column; pass
 * `adjustments`/`dimensions` to enrich the reporting ledgers without
 * affecting the stored total.
 */
class InvoiceService
{
    use ResolvesConfiguredModels;

    public function __construct(
        private readonly InvoiceNumberGeneratorContract $invoiceNumberGenerator,
    ) {}

    public function createForOrder(Order $order, ?string $invoiceNumber = null, ?string $idempotencyKey = null): Invoice
    {
        $class = static::model('invoice');

        /** @var Invoice $invoice */
        $invoice = $class::create([
            'invoice_number' => $invoiceNumber ?? $this->invoiceNumberGenerator->generate($order->branch_id),
            'idempotency_key' => $idempotencyKey,
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'branch_id' => $order->branch_id,
            'sales_unit_id' => $order->sales_unit_id,
            'warehouse_id' => $order->warehouse_id,
            'amount' => $order->total_amount,
            'invoice_date' => now()->toDateString(),
            'status' => 'issued',
            'financial_status' => 'issued',
        ]);

        CommerceEventDispatcher::dispatch(new InvoiceIssued(
            invoiceId: $invoice->id,
            orderId: $order->id,
            amount: $invoice->amount,
            userId: $order->user_id,
        ));

        return $invoice;
    }

    /**
     * Issue an invoice with no order at all (order_id null). Retry-safe
     * when idempotencyKey is set. `amount` is the final total — pass
     * `adjustments` only to enrich the reporting breakdown ledger (it does
     * not recompute `amount`); `dimensions` are written as
     * document_dimensions rows (in addition to the sales_unit_id/
     * warehouse_id shortcut columns).
     *
     * @param  array{
     *     amount: int|float,
     *     user_id?: int|string|null,
     *     branch_id?: int|string|null,
     *     sales_unit_id?: int|string|null,
     *     warehouse_id?: int|string|null,
     *     invoice_number?: string|null,
     *     idempotency_key?: string|null,
     *     extra_attributes?: array|null,
     *     adjustments?: list<array{key: string, sign?: int, amount: int|float, payload?: array|null}>,
     *     dimensions?: array<string, mixed>,
     * }  $data
     */
    public function issueStandalone(array $data): Invoice
    {
        return DB::transaction(function () use ($data): Invoice {
            $idempotencyKey = $data['idempotency_key'] ?? null;

            if ($idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    return $existing;
                }
            }

            $class = static::model('invoice');
            $branchId = $data['branch_id'] ?? null;

            /** @var Invoice $invoice */
            $invoice = $class::create([
                'invoice_number' => $data['invoice_number'] ?? $this->invoiceNumberGenerator->generate($branchId),
                'idempotency_key' => $idempotencyKey,
                'order_id' => null,
                'user_id' => $data['user_id'] ?? null,
                'branch_id' => $branchId,
                'sales_unit_id' => $data['sales_unit_id'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'amount' => (int) round($data['amount']),
                'invoice_date' => now()->toDateString(),
                'status' => 'issued',
                'financial_status' => 'issued',
                'extra_attributes' => $data['extra_attributes'] ?? null,
            ]);

            foreach ($data['adjustments'] ?? [] as $adjustment) {
                $invoice->adjustments()->create([
                    'key' => $adjustment['key'],
                    'sign' => $adjustment['sign'] ?? 1,
                    'amount' => (int) round($adjustment['amount']),
                    'payload' => $adjustment['payload'] ?? null,
                ]);
            }

            foreach ($data['dimensions'] ?? [] as $key => $value) {
                $invoice->addDimension($key, $value);
            }

            CommerceEventDispatcher::dispatch(new InvoiceIssued(
                invoiceId: $invoice->id,
                orderId: null,
                amount: $invoice->amount,
                userId: $invoice->user_id,
            ));

            return $invoice;
        });
    }

    private function findByIdempotencyKey(string $key): ?Invoice
    {
        $class = static::model('invoice');

        return $class::query()->where('idempotency_key', $key)->first();
    }
}
