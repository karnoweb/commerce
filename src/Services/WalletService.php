<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Karnoweb\Commerce\Exceptions\IdempotencyConflict;
use Karnoweb\Commerce\Models\Wallet;
use Karnoweb\Commerce\Models\WalletTransaction;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Package-safe wallet ledger: credit/debit against a polymorphic owner
 * (reference_type/reference_id), scoped per branch. No session/auth helpers,
 * no gateway calls — Commerce only records the outcome the host asks it to record.
 */
class WalletService
{
    use ResolvesConfiguredModels;

    /**
     * Find the owner's wallet for a branch, creating it (as primary) if missing.
     */
    public function findOrCreateWallet(string $ownerType, int|string $ownerId, int|string $branchId): Wallet
    {
        $walletClass = static::model('wallet');

        return $walletClass::query()->firstOrCreate(
            [
                'reference_type' => $ownerType,
                'reference_id' => $ownerId,
                'branch_id' => $branchId,
            ],
            ['primary' => true]
        );
    }

    /**
     * Credit (increase) the owner's wallet balance.
     *
     * @throws IdempotencyConflict
     */
    public function credit(
        int|string $ownerId,
        int|string $branchId,
        int|float $amount,
        ?string $description = null,
        ?Model $transactionable = null,
        ?string $idempotencyKey = null,
        ?string $ownerType = null,
        string $type = 'credit',
    ): WalletTransaction {
        return $this->move($ownerType ?? (string) static::model('user'), $ownerId, $branchId, $amount, 1, $type, $description, $transactionable, $idempotencyKey);
    }

    /**
     * Debit (decrease) the owner's wallet balance.
     *
     * @throws IdempotencyConflict
     */
    public function debit(
        int|string $ownerId,
        int|string $branchId,
        int|float $amount,
        ?string $description = null,
        ?Model $transactionable = null,
        ?string $idempotencyKey = null,
        ?string $ownerType = null,
        string $type = 'debit',
    ): WalletTransaction {
        return $this->move($ownerType ?? (string) static::model('user'), $ownerId, $branchId, $amount, -1, $type, $description, $transactionable, $idempotencyKey);
    }

    private function move(
        string $ownerType,
        int|string $ownerId,
        int|string $branchId,
        int|float $amount,
        int $sign,
        string $type,
        ?string $description,
        ?Model $transactionable,
        ?string $idempotencyKey,
    ): WalletTransaction {
        return DB::transaction(function () use ($ownerType, $ownerId, $branchId, $amount, $sign, $type, $description, $transactionable, $idempotencyKey): WalletTransaction {
            if ($idempotencyKey !== null) {
                $existing = $this->findByIdempotencyKey($idempotencyKey);

                if ($existing !== null) {
                    $this->assertSameMovePayload($existing, $amount, $sign, $idempotencyKey);

                    return $existing;
                }
            }

            $wallet = $this->findOrCreateWallet($ownerType, $ownerId, $branchId);

            $walletTransactionClass = static::model('wallet_transaction');

            return $walletTransactionClass::create([
                'wallet_id' => $wallet->id,
                'causer_id' => $ownerId,
                'amount' => abs($amount),
                'sign' => $sign,
                'type' => $type,
                'description' => $description,
                'published' => true,
                'idempotency_key' => $idempotencyKey,
                'transactionable_type' => $transactionable?->getMorphClass(),
                'transactionable_id' => $transactionable?->getKey(),
            ]);
        });
    }

    private function findByIdempotencyKey(string $key): ?WalletTransaction
    {
        $walletTransactionClass = static::model('wallet_transaction');

        return $walletTransactionClass::query()->where('idempotency_key', $key)->first();
    }

    private function assertSameMovePayload(WalletTransaction $existing, int|float $amount, int $sign, string $idempotencyKey): void
    {
        $sameAmount = (float) $existing->amount === (float) abs($amount);
        $sameSign = (int) $existing->sign === $sign;

        if (! $sameAmount || ! $sameSign) {
            throw new IdempotencyConflict($idempotencyKey);
        }
    }
}
