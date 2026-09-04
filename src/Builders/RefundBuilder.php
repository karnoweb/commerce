<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use InvalidArgumentException;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Services\RefundService;

/**
 * Fluent entry point for refunds: always creates an OrderReturn, optionally
 * credits a wallet, and — once the order's returns sum reaches its total —
 * transitions the order (and its PAID payments) to REFUNDED.
 *
 * @example
 * Commerce::refund()
 *     ->forOrder($order)
 *     ->amount(1_000_000)
 *     ->reason('customer_return')
 *     ->toWallet(userId: $userId, branchId: $branchId)
 *     ->idempotencyKey('refund:order:'.$order->id.':amount:1000000')
 *     ->process();
 */
class RefundBuilder
{
    private ?Order $order = null;

    private int|float|null $amount = null;

    private ?string $reason = null;

    /** @var array{user_id: int|string, branch_id: int|string}|null */
    private ?array $toWallet = null;

    private ?string $idempotencyKey = null;

    public function __construct(private readonly RefundService $refundService) {}

    public function forOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    public function amount(int|float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function reason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /** Credit the refunded amount to this owner's wallet when process() runs. $branchId defaults to 0 ("global"). */
    public function toWallet(int|string $userId, int|string $branchId = 0): self
    {
        $this->toWallet = ['user_id' => $userId, 'branch_id' => $branchId];

        return $this;
    }

    /** Optional DB-unique key for safe retries of process(). */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Create the OrderReturn (and wallet credit, if requested). Retry-safe
     * when idempotencyKey() is set: a second call with the same key and
     * payload returns the same OrderReturn without double-crediting.
     */
    public function process(): OrderReturn
    {
        if ($this->order === null || $this->amount === null) {
            throw new InvalidArgumentException('RefundBuilder::process() requires forOrder() and amount() before use.');
        }

        return $this->refundService->process(
            $this->order,
            $this->amount,
            $this->reason,
            $this->toWallet,
            $this->idempotencyKey,
        );
    }
}
