<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use InvalidArgumentException;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Services\InvoiceService;

/**
 * Fluent entry point for invoices. CheckoutBuilder::finalize() already
 * creates an order's mandatory invoice automatically — reach for this
 * builder to attach an *additional* invoice to an order, or to issue a
 * fully standalone invoice with no order at all. `amount()` is always the
 * final total; `taxAmount()`/`discountAmount()`/`addAdjustment()` are
 * shortcuts over the `document_adjustments` reporting ledger only — they
 * never recompute `amount`.
 *
 * @example
 * Commerce::invoices()->issueStandalone(amount: 500_000, userId: $userId, branchId: $branchId);
 */
class InvoiceBuilder
{
    private ?Order $order = null;

    private int|string|null $userId = null;

    private int|string|null $branchId = null;

    private int|string|null $salesUnitId = null;

    private int|string|null $warehouseId = null;

    private int|float|null $amount = null;

    /** @var list<array{key: string, sign: int, amount: int|float, payload: array|null}> */
    private array $adjustments = [];

    /** @var array<string, mixed> */
    private array $dimensions = [];

    private ?string $invoiceNumber = null;

    private ?string $idempotencyKey = null;

    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function forOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function forUser(int|string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function branchId(int|string $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    public function salesUnitId(int|string $salesUnitId): self
    {
        $this->salesUnitId = $salesUnitId;
        $this->dimensions['sales_unit_id'] = $salesUnitId;

        return $this;
    }

    public function warehouseId(int|string $warehouseId): self
    {
        $this->warehouseId = $warehouseId;
        $this->dimensions['warehouse_id'] = $warehouseId;

        return $this;
    }

    /** Arbitrary reporting dimension (region_id, channel_id, cashier_id, ...) — a document_dimensions row only. */
    public function addDimension(string $key, mixed $value): self
    {
        $this->dimensions[$key] = $value;

        return $this;
    }

    /** The final invoice total. */
    public function amount(int|float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /** Shortcut over document_adjustments (key=tax, sign=+1) — reporting breakdown only, does not affect amount(). */
    public function taxAmount(int|float $amount): self
    {
        $this->adjustments[] = ['key' => 'tax', 'sign' => 1, 'amount' => $amount, 'payload' => null];

        return $this;
    }

    /** Shortcut over document_adjustments (key=discount, sign=-1) — reporting breakdown only, does not affect amount(). */
    public function discountAmount(int|float $amount): self
    {
        $this->adjustments[] = ['key' => 'discount', 'sign' => -1, 'amount' => $amount, 'payload' => null];

        return $this;
    }

    /** Add an arbitrary document_adjustments row (fee, rounding, coupon, ...) — reporting breakdown only. */
    public function addAdjustment(string $key, int|float $amount, int $sign = 1, ?array $payload = null): self
    {
        $this->adjustments[] = ['key' => $key, 'sign' => $sign, 'amount' => $amount, 'payload' => $payload];

        return $this;
    }

    public function invoiceNumber(string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    /** Optional DB-unique key for safe retries. */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Attach an *additional* invoice to the order set via forOrder().
     * CheckoutBuilder::finalize() already creates the mandatory one.
     */
    public function create(): Invoice
    {
        if ($this->order === null) {
            throw new InvalidArgumentException('InvoiceBuilder::create() requires forOrder() before use.');
        }

        return $this->invoiceService->createForOrder($this->order, $this->invoiceNumber, $this->idempotencyKey);
    }

    /**
     * Issue an invoice with no order at all (order_id null). Named
     * arguments here override any prior fluent setter calls, so a single
     * one-shot call — issueStandalone(amount: 500_000, userId: $id, branchId: $id) —
     * works without chaining.
     */
    public function issueStandalone(
        int|float|null $amount = null,
        int|string|null $userId = null,
        int|string|null $branchId = null,
        int|string|null $salesUnitId = null,
        int|string|null $warehouseId = null,
        ?string $invoiceNumber = null,
        ?string $idempotencyKey = null,
    ): Invoice {
        $amount ??= $this->amount;

        if ($amount === null) {
            throw new InvalidArgumentException('InvoiceBuilder::issueStandalone() requires an amount.');
        }

        $dimensions = $this->dimensions;

        if ($salesUnitId !== null) {
            $dimensions['sales_unit_id'] = $salesUnitId;
        }

        if ($warehouseId !== null) {
            $dimensions['warehouse_id'] = $warehouseId;
        }

        return $this->invoiceService->issueStandalone([
            'amount' => $amount,
            'user_id' => $userId ?? $this->userId,
            'branch_id' => $branchId ?? $this->branchId,
            'sales_unit_id' => $salesUnitId ?? $this->salesUnitId,
            'warehouse_id' => $warehouseId ?? $this->warehouseId,
            'invoice_number' => $invoiceNumber ?? $this->invoiceNumber,
            'idempotency_key' => $idempotencyKey ?? $this->idempotencyKey,
            'adjustments' => $this->adjustments,
            'dimensions' => $dimensions,
        ]);
    }
}
