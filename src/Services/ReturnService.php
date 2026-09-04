<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Karnoweb\Commerce\DTOs\ReturnItemInput;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Events\ReturnCreated;
use Karnoweb\Commerce\Exceptions\CannotReturnWithoutItems;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Exceptions\ReturnItemNotFoundInOrder;
use Karnoweb\Commerce\Exceptions\ReturnQuantityExceedsAvailable;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Canonical quantity-based return flow: one or more ReturnItemInput lines,
 * each tied to an original sale line (OrderItem), are validated against how
 * much of that line is still returnable (sold - already returned) and
 * persisted as an OrderReturn + OrderReturnItem rows. Optionally credits a
 * wallet for the computed (or overridden) total. Once the sum of an order's
 * returns reaches its total, the order (and any PAID payments) transition to
 * REFUNDED — same rule RefundService uses, since both write to the same
 * order_returns.amount column.
 *
 * Distinct from the legacy, amount-only RefundService: use this service
 * ("returns", not "refund") whenever the caller knows *which* lines and
 * *how many* units are coming back — the API-first, most-businesses path.
 */
class ReturnService
{
    use ResolvesConfiguredModels;

    public function __construct(private readonly WalletService $walletService) {}

    /**
     * @param  list<ReturnItemInput>  $items
     * @param  array{user_id: int|string, branch_id: int|string}|null  $toWallet
     *
     * @throws CannotReturnWithoutItems
     * @throws ReturnItemNotFoundInOrder
     * @throws ReturnQuantityExceedsAvailable
     * @throws IdempotencyConflict
     */
    public function process(
        Order $order,
        array $items,
        ?string $idempotencyKey = null,
        ?array $toWallet = null,
        int|float|null $amountOverride = null,
    ): OrderReturn {
        return DB::transaction(function () use ($order, $items, $idempotencyKey, $toWallet, $amountOverride): OrderReturn {
            if ($idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSamePayload($existing, $order, $idempotencyKey);

                    return $existing;
                }
            }

            if ($items === []) {
                throw new CannotReturnWithoutItems($order->id);
            }

            $orderItemClass = static::model('order_item');
            $orderReturnItemClass = static::model('order_return_item');

            $lines = [];
            $totalAmount = 0.0;

            foreach ($items as $item) {
                $orderItem = $orderItemClass::query()
                    ->where('id', $item->orderItemId)
                    ->where('order_id', $order->id)
                    ->first();

                if ($orderItem === null) {
                    throw new ReturnItemNotFoundInOrder($item->orderItemId, $order->id);
                }

                $soldQuantity = (int) $orderItem->quantity;
                $alreadyReturned = (int) $orderReturnItemClass::query()
                    ->where('order_item_id', $orderItem->id)
                    ->sum('quantity');
                $available = $soldQuantity - $alreadyReturned;

                if ($item->quantity > $available) {
                    throw new ReturnQuantityExceedsAvailable($orderItem->id, $item->quantity, $available);
                }

                $unitPriceSnapshot = (float) ($orderItem->sale_price ?? $orderItem->price ?? 0);
                $lineAmount = $unitPriceSnapshot * $item->quantity;
                $totalAmount += $lineAmount;

                $lines[] = [
                    'order_item_id' => $orderItem->id,
                    'quantity' => $item->quantity,
                    'unit_price_snapshot' => $unitPriceSnapshot,
                    'amount' => $lineAmount,
                    'reason' => $item->reason,
                ];
            }

            $finalAmount = $amountOverride ?? $totalAmount;

            $orderReturnClass = static::model('order_return');

            /** @var OrderReturn $orderReturn */
            $orderReturn = $orderReturnClass::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'amount' => $finalAmount,
                'idempotency_key' => $idempotencyKey,
            ]);

            $eventItems = [];

            foreach ($lines as $line) {
                $returnItem = $orderReturnItemClass::create([
                    'order_return_id' => $orderReturn->id,
                    ...$line,
                ]);

                $eventItems[] = [
                    'orderItemId' => $returnItem->order_item_id,
                    'quantity' => $returnItem->quantity,
                    'amount' => $returnItem->amount,
                ];
            }

            if ($toWallet !== null) {
                $this->walletService->credit(
                    ownerId: $toWallet['user_id'],
                    branchId: $toWallet['branch_id'],
                    amount: $finalAmount,
                    description: 'order_return',
                    transactionable: $orderReturn,
                    idempotencyKey: $idempotencyKey,
                    type: 'return',
                );
            }

            $this->maybeTransitionOrderToRefunded($order);

            CommerceEventDispatcher::dispatch(new ReturnCreated(
                orderId: $order->id,
                orderReturnId: $orderReturn->id,
                totalAmount: $finalAmount,
                items: $eventItems,
                userId: $order->user_id,
            ));

            return $orderReturn;
        });
    }

    /**
     * Once the sum of an order's returns (from either ReturnService or the
     * legacy RefundService — both write to order_returns.amount) reaches
     * the order total, flip the order and its PAID payments to REFUNDED.
     */
    private function maybeTransitionOrderToRefunded(Order $order): void
    {
        $orderReturnClass = static::model('order_return');

        $alreadyReturned = (float) $orderReturnClass::query()->where('order_id', $order->id)->sum('amount');

        if ($alreadyReturned >= (float) $order->total) {
            $order->update(['status' => OrderStatusEnum::REFUNDED]);

            $paymentClass = static::model('payment');

            $paymentClass::query()
                ->where('order_id', $order->id)
                ->where('status', PaymentStatusEnum::PAID)
                ->update(['status' => PaymentStatusEnum::REFUNDED]);
        }
    }

    private function findByIdempotencyKey(string $key): ?OrderReturn
    {
        $orderReturnClass = static::model('order_return');

        return $orderReturnClass::query()->where('idempotency_key', $key)->first();
    }

    private function assertSamePayload(OrderReturn $existing, Order $order, string $idempotencyKey): void
    {
        if ((string) $existing->order_id !== (string) $order->id) {
            throw new IdempotencyConflict($idempotencyKey);
        }
    }
}
