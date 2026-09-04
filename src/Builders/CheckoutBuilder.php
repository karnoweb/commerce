<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use InvalidArgumentException;
use Karnoweb\Commerce\DTOs\CheckoutResult;
use Karnoweb\Commerce\DTOs\CheckoutResultWithPayments;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Services\CheckoutService;

/**
 * Fluent entry point for placing an order from a user's cart.
 *
 * Cart source: order_lines where order_id IS NULL for that user.
 * finalize() always creates the order's mandatory invoice — Commerce
 * never leaves an order unbilled. finalizeWithPayments() also writes
 * 1..n PENDING payment records (no gateway). Confirmation is a
 * separate Commerce::payments()->confirm() step.
 *
 * branchId(null) (or omitting branchId()) is resolved from the bound
 * CommerceContextResolverContract when the host registered one.
 *
 * @example
 * $result = Commerce::checkout()
 *     ->forUser($userId)
 *     ->branchId($branchId)
 *     ->salesUnitId($salesUnitId)
 *     ->warehouseId($warehouseId)
 *     ->shippingAmount(50_000)
 *     ->taxAmount(90_000)
 *     ->idempotencyKey('checkout:user:9001:cart:active')
 *     ->finalize();
 *
 * $result->order;   // Order
 * $result->invoice; // Invoice (always present)
 */
class CheckoutBuilder
{
    private int|string|null $userId = null;

    private ?Order $order = null;

    private int|string|null $branchId = null;

    private int|string|null $salesUnitId = null;

    private int|string|null $warehouseId = null;

    private int|float $shippingAmount = 0;

    private int|float $taxAmount = 0;

    private int|float $discountAmount = 0;

    /** @var list<array{key: string, sign: int, amount: int|float, payload: array|null}> */
    private array $customAdjustments = [];

    /** @var array<string, mixed> */
    private array $dimensions = [];

    private ?string $orderNumber = null;

    private ?string $idempotencyKey = null;

    private ?string $currency = null;

    private ?string $note = null;

    private ?string $invoiceNumber = null;

    public function __construct(private readonly CheckoutService $checkoutService) {}

    /** Set the checkout owner. Required before finalize(). */
    public function forUser(int|string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /** Target an existing order instead of placing a new one (used by createInvoice()). */
    public function forOrder(Order $order): self
    {
        $this->order = $order;
        $this->userId = $order->user_id;

        return $this;
    }

    /**
     * Soft branch key. Pass null (or omit this call) to let the bound
     * CommerceContextResolverContract supply a default.
     */
    public function branchId(int|string|null $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    /** Reporting dimension: which sales unit sold this order. Writes orders.sales_unit_id AND a document_dimensions row. */
    public function salesUnitId(int|string $salesUnitId): self
    {
        $this->salesUnitId = $salesUnitId;
        $this->dimensions['sales_unit_id'] = $salesUnitId;

        return $this;
    }

    /** Reporting dimension: which warehouse is involved. Writes orders.warehouse_id AND a document_dimensions row. */
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

    /** @deprecated Prefer addDimension(). Kept as a thin alias. */
    public function addContext(string $key, mixed $value): self
    {
        return $this->addDimension($key, $value);
    }

    /** Shortcut over document_adjustments: key=shipping, sign=+1. Always recorded, even when 0. */
    public function shippingAmount(int|float $amount): self
    {
        $this->shippingAmount = $amount;

        return $this;
    }

    /** Shortcut over document_adjustments: key=tax, sign=+1. Always recorded, even when 0. */
    public function taxAmount(int|float $amount): self
    {
        $this->taxAmount = $amount;

        return $this;
    }

    /** Shortcut over document_adjustments: key=discount, sign=-1. Always recorded, even when 0. */
    public function discountAmount(int|float $amount): self
    {
        $this->discountAmount = $amount;

        return $this;
    }

    /**
     * Add an arbitrary document_adjustments row (fee, rounding, coupon, a
     * host-defined key, ...) beyond the shipping/tax/discount shortcuts.
     */
    public function addAdjustment(string $key, int|float $amount, int $sign = 1, ?array $payload = null): self
    {
        $this->customAdjustments[] = ['key' => $key, 'sign' => $sign, 'amount' => $amount, 'payload' => $payload];

        return $this;
    }

    /**
     * Override the OrderNumberGeneratorContract. When omitted, the
     * configured sequential generator supplies the value.
     */
    public function orderNumber(string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    /** Optional DB-unique key for safe retries of finalize(). */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    public function currency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function note(string $note): self
    {
        $this->note = $note;

        return $this;
    }

    /**
     * Override the InvoiceNumberGeneratorContract for the mandatory
     * invoice finalize() creates.
     */
    public function invoiceNumber(string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;

        return $this;
    }

    /**
     * Create the order from the user's cart, attach the cart lines, record
     * shipping/tax/discount + any custom adjustments, write sales unit /
     * warehouse / custom dimensions, and create the order's mandatory
     * invoice — all in one transaction, dispatching OrderCreated +
     * InvoiceIssued after commit. Retry-safe when idempotencyKey() is set:
     * a second call with the same key returns the same CheckoutResult.
     */
    public function finalize(): CheckoutResult
    {
        if ($this->userId === null) {
            throw new InvalidArgumentException('CheckoutBuilder::finalize() requires forUser() before use.');
        }

        $result = $this->checkoutService->finalize($this->checkoutPayload());

        $this->order = $result->order;

        return $result;
    }

    /**
     * Same as finalize(), then create 1..n PENDING payment records
     * against the new invoice. Each item may include method_id, type,
     * amount, extra (stored on payments.extra_attributes), and an
     * optional per-payment idempotency_key.
     *
     * @param  list<array{method_id?: int|string|null, type?: PaymentTypeEnum|string, amount: int|float, extra?: array<string, mixed>, idempotency_key?: string|null}>  $payments
     */
    public function finalizeWithPayments(array $payments): CheckoutResultWithPayments
    {
        if ($this->userId === null) {
            throw new InvalidArgumentException('CheckoutBuilder::finalizeWithPayments() requires forUser() before use.');
        }

        $result = $this->checkoutService->finalizeWithPayments($this->checkoutPayload(), $payments);
        $this->order = $result->order;

        return $result;
    }

    /** @deprecated Alias for finalize(). Kept for backward compatibility. */
    public function place(): CheckoutResult
    {
        return $this->finalize();
    }

    /**
     * @return array{
     *     user_id: int|string,
     *     branch_id: int|string|null,
     *     sales_unit_id: int|string|null,
     *     warehouse_id: int|string|null,
     *     order_number: string|null,
     *     idempotency_key: string|null,
     *     currency: string|null,
     *     note: string|null,
     *     invoice_number: string|null,
     *     adjustments: list<array{key: string, sign: int, amount: int|float, payload: array|null}>,
     *     dimensions: array<string, mixed>,
     * }
     */
    private function checkoutPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'branch_id' => $this->branchId,
            'sales_unit_id' => $this->salesUnitId,
            'warehouse_id' => $this->warehouseId,
            'order_number' => $this->orderNumber,
            'idempotency_key' => $this->idempotencyKey,
            'currency' => $this->currency,
            'note' => $this->note,
            'invoice_number' => $this->invoiceNumber,
            'adjustments' => [
                ['key' => 'shipping', 'sign' => 1, 'amount' => $this->shippingAmount, 'payload' => null],
                ['key' => 'tax', 'sign' => 1, 'amount' => $this->taxAmount, 'payload' => null],
                ['key' => 'discount', 'sign' => -1, 'amount' => $this->discountAmount, 'payload' => null],
                ...$this->customAdjustments,
            ],
            'dimensions' => $this->dimensions,
        ];
    }

    /**
     * Attach an *additional* invoice to the current order (set via
     * forOrder() or a prior finalize() call in this builder instance).
     * finalize() already creates the mandatory one — use this only when a
     * second invoice is genuinely needed.
     */
    public function createInvoice(?string $invoiceNumber = null): Invoice
    {
        if ($this->order === null) {
            throw new InvalidArgumentException('CheckoutBuilder::createInvoice() requires forOrder() or a prior finalize() call.');
        }

        return $this->checkoutService->createInvoice($this->order, $invoiceNumber);
    }
}
