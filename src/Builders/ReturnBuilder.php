<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use InvalidArgumentException;
use Karnoweb\Commerce\DTOs\ReturnLineInput;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Services\ReturnService;

/**
 * Fluent entry point for quantity-based returns: addLine() queues one
 * returned quantity against an original sale line (an OrderLine id);
 * finalizeRefund*() validates all queued lines together, persists the
 * OrderReturn + OrderReturnLine rows in one transaction, and (optionally)
 * credits a wallet. Prefer this over Commerce::refund() when you know
 * which lines and how many units are coming back.
 *
 * @example
 * $return = Commerce::returns()
 *     ->forOrder($order)
 *     ->idempotencyKey('return:order:'.$order->id.':v1')
 *     ->addLine(orderLineId: $order->lines()->first()->id, quantity: 1, reasonNote: 'Customer return')
 *     ->finalizeRefundToWallet(userId: $userId, branchId: $branchId);
 */
class ReturnBuilder
{
    private ?Order $order = null;

    /** @var list<ReturnLineInput> */
    private array $lines = [];

    private ?string $idempotencyKey = null;

    public function __construct(private readonly ReturnService $returnService) {}

    public function forOrder(Order $order): self
    {
        $this->order = $order;

        return $this;
    }

    /** Optional DB-unique key for safe retries of finalizeRefund*(). */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Queue a returned quantity against an original sale line (OrderLine
     * id). Call multiple times to return several lines in one OrderReturn.
     * Not persisted until finalizeRefund*() is called. $returnReasonId is
     * a soft/internal reference to a ReturnReason row (see
     * database/seeders/CommerceSeeder.php for seeded defaults);
     * $reasonNote is an optional free-text note alongside it.
     */
    public function addLine(int|string $orderLineId, int|float $quantity, int|string|null $returnReasonId = null, ?string $reasonNote = null): self
    {
        $this->lines[] = new ReturnLineInput($orderLineId, $quantity, $returnReasonId, $reasonNote);

        return $this;
    }

    /**
     * @deprecated Prefer addLine(). Kept as a thin alias.
     */
    public function addItem(int|string $orderLineId, int|float $quantity, int|string|null $returnReasonId = null, ?string $reasonNote = null): self
    {
        return $this->addLine($orderLineId, $quantity, $returnReasonId, $reasonNote);
    }

    /**
     * Validate and persist the queued lines as an OrderReturn, crediting
     * the given owner's wallet for the computed total. $branchId defaults
     * to 0 ("global" — see Wallet's branch_id convention).
     */
    public function finalizeRefundToWallet(int|string $userId, int|string $branchId = 0): OrderReturn
    {
        $this->assertReady();

        return $this->returnService->process(
            $this->order,
            $this->lines,
            $this->idempotencyKey,
            ['user_id' => $userId, 'branch_id' => $branchId],
        );
    }

    /** @deprecated Prefer finalizeRefundToWallet(). Kept as a thin alias. */
    public function finalizeAndRefundToWallet(int|string $userId, int|string $branchId = 0): OrderReturn
    {
        return $this->finalizeRefundToWallet($userId, $branchId);
    }

    /**
     * Validate and persist the queued lines as an OrderReturn without
     * crediting a wallet — the host handles the actual refund payout
     * (cash, gateway refund, ...) itself. $amountOverride replaces the
     * amount computed from quantities x unit price snapshots when set.
     */
    public function finalizeRefund(int|float|null $amountOverride = null): OrderReturn
    {
        $this->assertReady();

        return $this->returnService->process(
            $this->order,
            $this->lines,
            $this->idempotencyKey,
            null,
            $amountOverride,
        );
    }

    /** @deprecated Prefer finalizeRefund(). Kept as a thin alias. */
    public function finalizeAndRefund(int|float|null $amountOverride = null): OrderReturn
    {
        return $this->finalizeRefund($amountOverride);
    }

    private function assertReady(): void
    {
        if ($this->order === null) {
            throw new InvalidArgumentException('ReturnBuilder requires forOrder() before use.');
        }
    }
}
