<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Support\Facades\DB;
use Karnoweb\Commerce\DTOs\ReturnLineInput;
use Karnoweb\Commerce\DTOs\ReturnResult;
use Karnoweb\Commerce\Enums\FinancialStatusEnum;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Events\ReturnCreated;
use Karnoweb\Commerce\Exceptions\CannotReturnWithoutLines;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Exceptions\ReturnLineNotFoundInOrder;
use Karnoweb\Commerce\Exceptions\ReturnQuantityExceedsAvailable;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\OrderReturn;
use Karnoweb\Commerce\Models\Wallet;
use Karnoweb\Commerce\Models\WalletTransaction;
use Karnoweb\Commerce\Support\CommerceEventDispatcher;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;
use RuntimeException;

/**
 * Canonical quantity-based return flow: one or more ReturnLineInput lines,
 * each tied to an original sale line (OrderLine), are validated against how
 * much of that line is still returnable (sold - already returned) and
 * persisted as an OrderReturn + OrderReturnLine rows. Optionally credits a
 * wallet for the computed (or overridden) total. Once the sum of an order's
 * returns reaches its total AND the order is paid, the order (and any PAID
 * payments) transition to REFUNDED — pending → refunded is not allowed.
 *
 * Distinct from the legacy, amount-only RefundService: use this service
 * ("returns", not "refund") whenever the caller knows *which* lines and
 * *how many* units are coming back — the API-first, most-businesses path.
 */
class ReturnService
{
    use ResolvesConfiguredModels;

    public function __construct(
        private readonly WalletService $walletService,
        private readonly OrderService $orderService,
    ) {}

    /**
     * @param  list<ReturnLineInput>  $lines
     * @param  array{user_id: int|string, branch_id: int|string}|null  $toWallet
     *
     * @throws CannotReturnWithoutLines
     * @throws ReturnLineNotFoundInOrder
     * @throws ReturnQuantityExceedsAvailable
     * @throws IdempotencyConflict
     */
    public function process(
        Order $order,
        array $lines,
        ?string $idempotencyKey = null,
        ?array $toWallet = null,
        int|float|null $amountOverride = null,
    ): OrderReturn {
        return $this->processInternal($order, $lines, $idempotencyKey, $toWallet, $amountOverride)['orderReturn'];
    }

    /**
     * Same as process() with a required wallet credit; returns the wallet
     * transaction details alongside the OrderReturn.
     *
     * @param  list<ReturnLineInput>  $lines
     * @param  array{user_id: int|string, branch_id: int|string}  $toWallet
     */
    public function processToWallet(
        Order $order,
        array $lines,
        array $toWallet,
        ?string $idempotencyKey = null,
        int|float|null $amountOverride = null,
    ): ReturnResult {
        $result = $this->processInternal($order, $lines, $idempotencyKey, $toWallet, $amountOverride);

        if ($result['wallet'] === null || $result['walletTransaction'] === null) {
            throw new RuntimeException('Return refund to wallet did not produce a wallet transaction.');
        }

        return new ReturnResult($result['orderReturn'], $result['wallet'], $result['walletTransaction']);
    }

    /**
     * @param  list<ReturnLineInput>  $lines
     * @param  array{user_id: int|string, branch_id: int|string}|null  $toWallet
     * @return array{orderReturn: OrderReturn, wallet: Wallet|null, walletTransaction: WalletTransaction|null}
     */
    private function processInternal(
        Order $order,
        array $lines,
        ?string $idempotencyKey,
        ?array $toWallet,
        int|float|null $amountOverride,
    ): array {
        return DB::transaction(function () use ($order, $lines, $idempotencyKey, $toWallet, $amountOverride): array {
            if ($idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSamePayload($existing, $order, $idempotencyKey);

                    return $this->hydrateWalletResult($existing);
                }
            }

            if ($lines === []) {
                throw new CannotReturnWithoutLines($order->id);
            }

            $orderLineClass = static::model('order_line');
            $orderReturnLineClass = static::model('order_return_line');

            $computedLines = [];
            $totalAmount = 0;

            foreach ($lines as $lineInput) {
                /** @var ReturnLineInput $lineInput */
                $orderLine = $orderLineClass::query()
                    ->where('id', $lineInput->orderLineId)
                    ->where('order_id', $order->id)
                    ->first();

                if ($orderLine === null) {
                    throw new ReturnLineNotFoundInOrder($lineInput->orderLineId, $order->id);
                }

                $soldQuantity = (float) $orderLine->quantity;
                $alreadyReturned = (float) $orderReturnLineClass::query()
                    ->where('order_line_id', $orderLine->id)
                    ->sum('quantity');
                $available = round($soldQuantity - $alreadyReturned, 6);
                $requestedQuantity = round((float) $lineInput->quantity, 6);

                if ($requestedQuantity > $available + 1e-9) {
                    throw new ReturnQuantityExceedsAvailable($orderLine->id, $requestedQuantity, $available);
                }

                $unitPriceAmount = (int) $orderLine->unit_price_amount;
                $lineAmount = (int) round($unitPriceAmount * $requestedQuantity);
                $totalAmount += $lineAmount;

                $computedLines[] = [
                    'order_line_id' => $orderLine->id,
                    'quantity' => $requestedQuantity,
                    'unit_price_amount' => $unitPriceAmount,
                    'amount' => $lineAmount,
                    'return_reason_id' => $lineInput->returnReasonId,
                    'reason_note' => $lineInput->reasonNote,
                ];
            }

            $finalAmount = $amountOverride !== null ? (int) round($amountOverride) : $totalAmount;

            $orderReturnClass = static::model('order_return');

            /** @var OrderReturn $orderReturn */
            $orderReturn = $orderReturnClass::create([
                'order_id' => $order->id,
                'total_amount' => $finalAmount,
                'idempotency_key' => $idempotencyKey,
            ]);

            $eventLines = [];

            foreach ($computedLines as $line) {
                $returnLine = $orderReturnLineClass::create([
                    'order_return_id' => $orderReturn->id,
                    ...$line,
                ]);

                $eventLines[] = [
                    'orderLineId' => $returnLine->order_line_id,
                    'quantity' => $returnLine->quantity,
                    'amount' => $returnLine->amount,
                ];
            }

            $wallet = null;
            $walletTransaction = null;

            if ($toWallet !== null) {
                $walletTransaction = $this->walletService->credit(
                    ownerId: $toWallet['user_id'],
                    branchId: $toWallet['branch_id'],
                    amount: $finalAmount,
                    description: 'order_return',
                    transactionable: $orderReturn,
                    idempotencyKey: $idempotencyKey,
                    type: 'return',
                );
                $walletTransaction->load('wallet');
                $wallet = $walletTransaction->wallet;
            }

            $this->maybeTransitionOrderToRefunded($order);

            CommerceEventDispatcher::dispatch(new ReturnCreated(
                orderId: $order->id,
                orderReturnId: $orderReturn->id,
                totalAmount: $finalAmount,
                lines: $eventLines,
                userId: $order->user_id,
            ));

            return [
                'orderReturn' => $orderReturn,
                'wallet' => $wallet,
                'walletTransaction' => $walletTransaction,
            ];
        });
    }

    /**
     * Once the sum of an order's returns reaches the order total *and*
     * the order is already paid, flip the order and its PAID payments to
     * REFUNDED. An unpaid (pending) order is left pending — pending →
     * refunded is not a legal financial transition.
     */
    private function maybeTransitionOrderToRefunded(Order $order): void
    {
        $orderReturnClass = static::model('order_return');

        $alreadyReturned = (int) $orderReturnClass::query()->where('order_id', $order->id)->sum('total_amount');

        if ($alreadyReturned < (int) $order->total_amount) {
            return;
        }

        $order->refresh();

        if ($order->financial_status !== FinancialStatusEnum::PAID) {
            return;
        }

        $this->orderService->transitionTo($order, FinancialStatusEnum::REFUNDED);

        $paymentClass = static::model('payment');

        $paymentClass::query()
            ->where('order_id', $order->id)
            ->where('status', PaymentStatusEnum::PAID)
            ->update(['status' => PaymentStatusEnum::REFUNDED]);

        $invoiceClass = static::model('invoice');

        $invoiceClass::query()
            ->where('order_id', $order->id)
            ->where('financial_status', 'paid')
            ->update(['financial_status' => 'refunded']);
    }

    /**
     * @return array{orderReturn: OrderReturn, wallet: Wallet|null, walletTransaction: WalletTransaction|null}
     */
    private function hydrateWalletResult(OrderReturn $orderReturn): array
    {
        $walletTransactionClass = static::model('wallet_transaction');

        /** @var WalletTransaction|null $tx */
        $tx = $walletTransactionClass::query()
            ->where('transactionable_type', $orderReturn->getMorphClass())
            ->where('transactionable_id', $orderReturn->id)
            ->latest('id')
            ->first();

        if ($tx !== null) {
            $tx->load('wallet');
        }

        return [
            'orderReturn' => $orderReturn,
            'wallet' => $tx?->wallet,
            'walletTransaction' => $tx,
        ];
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
