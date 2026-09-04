<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Events\RefundCreated;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Exceptions\RefundAmountExceedsPaidAmount;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Legacy amount-only refund flow: always creates an OrderReturn (with no
 * OrderReturnLine rows — a header-only ledger entry); optionally credits a
 * wallet. Once the sum of an order's returns reaches its total, the order
 * (and any PAID payments) transition to REFUNDED. Prefer
 * Commerce::returns() when the caller knows *which* lines and *how many*
 * units are coming back.
 */
class RefundService
{
    use ResolvesConfiguredModels;

    public function __construct(private readonly WalletService $walletService) {}

    /**
     * @param  array{user_id: int|string, branch_id: int|string}|null  $toWallet
     *
     * @throws RefundAmountExceedsPaidAmount
     * @throws IdempotencyConflict
     */
    public function process(
        Order $order,
        int|float $amount,
        ?string $reason = null,
        ?array $toWallet = null,
        ?string $idempotencyKey = null,
    ): OrderReturn {
        return DB::transaction(function () use ($order, $amount, $reason, $toWallet, $idempotencyKey): OrderReturn {
            if ($idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSameRefundPayload($existing, $order, $amount, $idempotencyKey);

                    return $existing;
                }
            }

            $amountInt = (int) round($amount);
            $alreadyRefunded = $this->refundedAmount($order);
            $available = (int) $order->total_amount - $alreadyRefunded;

            if ($amountInt > $available) {
                throw new RefundAmountExceedsPaidAmount($order->id, (float) $amountInt, (float) $available);
            }

            $orderReturnClass = static::model('order_return');

            /** @var OrderReturn $orderReturn */
            $orderReturn = $orderReturnClass::create([
                'order_id' => $order->id,
                'total_amount' => $amountInt,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($toWallet !== null) {
                $this->walletService->credit(
                    ownerId: $toWallet['user_id'],
                    branchId: $toWallet['branch_id'],
                    amount: $amountInt,
                    description: $reason,
                    transactionable: $orderReturn,
                    idempotencyKey: $idempotencyKey,
                    type: 'refund',
                );
            }

            if ($alreadyRefunded + $amountInt >= (int) $order->total_amount) {
                $order->update(['status' => OrderStatusEnum::REFUNDED]);

                $paymentClass = static::model('payment');

                $paymentClass::query()
                    ->where('order_id', $order->id)
                    ->where('status', PaymentStatusEnum::PAID)
                    ->update(['status' => PaymentStatusEnum::REFUNDED]);
            }

            CommerceEventDispatcher::dispatch(new RefundCreated(
                orderId: $order->id,
                orderReturnId: $orderReturn->id,
                amount: $amountInt,
                userId: $order->user_id,
            ));

            return $orderReturn;
        });
    }

    private function refundedAmount(Order $order): int
    {
        $orderReturnClass = static::model('order_return');

        return (int) $orderReturnClass::query()->where('order_id', $order->id)->sum('total_amount');
    }

    private function findByIdempotencyKey(string $key): ?OrderReturn
    {
        $orderReturnClass = static::model('order_return');

        return $orderReturnClass::query()->where('idempotency_key', $key)->first();
    }

    private function assertSameRefundPayload(OrderReturn $existing, Order $order, int|float $amount, string $idempotencyKey): void
    {
        $sameOrder = (string) $existing->order_id === (string) $order->id;
        $sameAmount = (int) $existing->total_amount === (int) round($amount);

        if (! $sameOrder || ! $sameAmount) {
            throw new IdempotencyConflict($idempotencyKey);
        }
    }
}
