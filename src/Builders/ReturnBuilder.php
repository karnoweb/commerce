<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use InvalidArgumentException;
use Karnoweb\Commerce\DTOs\ReturnItemInput;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Services\ReturnService;

/**
 * Fluent entry point for quantity-based returns: addItem() queues one
 * returned quantity against an original sale line; finalizeAndRefund*()
 * validates all queued lines together, persists the OrderReturn +
 * OrderReturnItem rows in one transaction, and (optionally) credits a
 * wallet. Prefer this over Commerce::refund() when you know which lines
 * and how many units are coming back.
 *
 * @example
 * $return = Commerce::returns()
 *     ->forOrder($order)
 *     ->idempotencyKey('return:order:'.$order->id.':v1')
 *     ->addItem(orderItemId: $order->items()->first()->id, quantity: 1, reason: 'Customer return')
 *     ->finalizeAndRefundToWallet(userId: $userId, branchId: $branchId);
 */
class ReturnBuilder
{
    private ?Order $order = null;

    /** @var list<ReturnItemInput> */
    private array $items = [];

    private ?string $idempotencyKey = null;

    public function __construct(private readonly ReturnService $returnService) {}

    public function forOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    /** Optional DB-unique key for safe retries of finalizeAndRefund*(). */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Queue a returned quantity against an original sale line. Call
     * multiple times to return several lines in one OrderReturn. Not
     * persisted until finalizeAndRefund*() is called.
     */
    public function addItem(int|string $orderItemId, int $quantity, ?string $reason = null): self
    {
        $this->items[] = new ReturnItemInput($orderItemId, $quantity, $reason);

        return $this;
    }

    /**
     * Validate and persist the queued lines as an OrderReturn, crediting
     * the given owner's wallet for the computed total.
     */
    public function finalizeAndRefundToWallet(int|string $userId, int|string|null $branchId = null): OrderReturn
    {
        $this->assertReady();

        if ($branchId === null) {
            throw new InvalidArgumentException('ReturnBuilder::finalizeAndRefundToWallet() requires a branchId.');
        }

        return $this->returnService->process(
            $this->order,
            $this->items,
            $this->idempotencyKey,
            ['user_id' => $userId, 'branch_id' => $branchId],
        );
    }

    /**
     * Validate and persist the queued lines as an OrderReturn without
     * crediting a wallet — the host handles the actual refund payout
     * (cash, gateway refund, ...) itself. $amountOverride replaces the
     * amount computed from quantities x unit price snapshots when set.
     */
    public function finalizeAndRefund(int|float|null $amountOverride = null): OrderReturn
    {
        $this->assertReady();

        return $this->returnService->process(
            $this->order,
            $this->items,
            $this->idempotencyKey,
            null,
            $amountOverride,
        );
    }

    private function assertReady(): void
    {
        if ($this->order === null) {
            throw new InvalidArgumentException('ReturnBuilder requires forOrder() before use.');
        }
    }
}
