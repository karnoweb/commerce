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
 * Canonical refund flow: always creates an OrderReturn; optionally credits
 * a wallet. Once the sum of an order's returns reaches its total, the order
 * (and any PAID payments) transition to REFUNDED. Partial refunds leave the
 * order PAID — callers rely on the returns sum, not a new enum value.
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

            $alreadyRefunded = $this->refundedAmount($order);
            $available = (float) $order->total - $alreadyRefunded;

            if ((float) $amount > $available) {
                throw new RefundAmountExceedsPaidAmount($order->id, (float) $amount, $available);
            }

            $orderReturnClass = static::model('order_return');

            /** @var OrderReturn $orderReturn */
            $orderReturn = $orderReturnClass::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'amount' => $amount,
                'reason' => $reason,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($toWallet !== null) {
                $this->walletService->credit(
                    ownerId: $toWallet['user_id'],
                    branchId: $toWallet['branch_id'],
                    amount: $amount,
                    description: $reason,
                    transactionable: $orderReturn,
                    idempotencyKey: $idempotencyKey,
                    type: 'refund',
                );
            }

            if ($alreadyRefunded + (float) $amount >= (float) $order->total) {
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
                amount: $amount,
                userId: $order->user_id,
            ));

            return $orderReturn;
        });
    }

    private function refundedAmount(Order $order): float
    {
        $orderReturnClass = static::model('order_return');

        return (float) $orderReturnClass::query()->where('order_id', $order->id)->sum('amount');
    }

    private function findByIdempotencyKey(string $key): ?OrderReturn
    {
        $orderReturnClass = static::model('order_return');

        return $orderReturnClass::query()->where('idempotency_key', $key)->first();
    }

    private function assertSameRefundPayload(OrderReturn $existing, Order $order, int|float $amount, string $idempotencyKey): void
    {
        $sameOrder = (string) $existing->order_id === (string) $order->id;
        $sameAmount = (float) $existing->amount === (float) $amount;

        if (! $sameOrder || ! $sameAmount) {
            throw new IdempotencyConflict($idempotencyKey);
        }
    }
}
