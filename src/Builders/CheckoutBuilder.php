<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use InvalidArgumentException;
use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Services\CheckoutService;

/**
 * Fluent entry point for placing an order from a user's cart and (optionally)
 * creating its invoice. Commerce never talks to a payment gateway — pair
 * this with Commerce::payment() once the host has an outcome to report.
 *
 * @example
 * $order = Commerce::checkout()
 *     ->forUser($userId)
 *     ->branchId($branchId)
 *     ->shippingAmount(50_000)
 *     ->idempotencyKey('checkout:user:9001:cart:active')
 *     ->place();
 *
 * $invoice = Commerce::checkout()->forOrder($order)->createInvoice();
 */
class CheckoutBuilder
{
    private int|string|null $userId = null;

    private ?Order $order = null;

    private int|string|null $branchId = null;

    private int|float $shippingAmount = 0;

    private int|float $taxAmount = 0;

    private int|float $discountAmount = 0;

    private ?string $orderNumber = null;

    private ?string $idempotencyKey = null;

    public function __construct(private readonly CheckoutService $checkoutService) {}

    /** Set the checkout owner. Required before place(). */
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

    public function branchId(int|string $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    public function shippingAmount(int|float $amount): self
    {
        $this->shippingAmount = $amount;

        return $this;
    }

    public function taxAmount(int|float $amount): self
    {
        $this->taxAmount = $amount;

        return $this;
    }

    public function discountAmount(int|float $amount): self
    {
        $this->discountAmount = $amount;

        return $this;
    }

    /** Override the generated order number. */
    public function orderNumber(string $orderNumber): self
    {
        $this->orderNumber = $orderNumber;

        return $this;
    }

    /** Optional DB-unique key for safe retries of place(). */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Create the order from the user's cart, attach the cart items, and
     * dispatch OrderCreated after commit. Retry-safe when idempotencyKey()
     * is set: a second call with the same key returns the same order.
     */
    public function place(): Order
    {
        if ($this->userId === null) {
            throw new InvalidArgumentException('CheckoutBuilder::place() requires forUser() before use.');
        }

        $this->order = $this->checkoutService->placeFromCart([
            'user_id' => $this->userId,
            'branch_id' => $this->branchId,
            'shipping_amount' => $this->shippingAmount,
            'tax_amount' => $this->taxAmount,
            'discount_amount' => $this->discountAmount,
            'order_number' => $this->orderNumber,
            'idempotency_key' => $this->idempotencyKey,
        ]);

        return $this->order;
    }

    /**
     * Optional package-safe invoice for the current order (set via
     * forOrder() or a prior place() call in this builder instance).
     */
    public function createInvoice(?string $invoiceNumber = null): Invoice
    {
        if ($this->order === null) {
            throw new InvalidArgumentException('CheckoutBuilder::createInvoice() requires forOrder() or a prior place() call.');
        }

        return $this->checkoutService->createInvoice($this->order, $invoiceNumber);
    }
}
