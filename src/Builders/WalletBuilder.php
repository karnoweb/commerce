<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Karnoweb\Commerce\Models\WalletTransaction;
use Karnoweb\Commerce\Services\WalletService;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Fluent entry point for wallet credit/debit. Owner is polymorphic
 * (reference_type/reference_id); forUser() targets the configured
 * `commerce.models.user` type, or use for() for any other owner model.
 *
 * @example
 * Commerce::wallet()
 *     ->forUser($userId)
 *     ->branchId($branchId)
 *     ->credit(100_000, description: 'promo bonus')
 *     ->idempotencyKey('wallet:credit:promo:9001')
 *     ->save();
 */
class WalletBuilder
{
    use ResolvesConfiguredModels;

    private int|string|null $ownerId = null;

    private ?string $ownerType = null;

    private int|string|null $branchId = null;

    private int|float|null $amount = null;

    private int $sign = 1;

    private string $type = 'credit';

    private ?string $description = null;

    private ?Model $transactionable = null;

    private ?string $idempotencyKey = null;

    public function __construct(private readonly WalletService $walletService) {}

    /** Target the configured commerce.models.user wallet owner. */
    public function forUser(int|string $userId): self
    {
        $this->ownerId = $userId;
        $this->ownerType = static::model('user');

        return $this;
    }

    /** Target any other polymorphic wallet owner (morph class + id). */
    public function for(string $ownerType, int|string $ownerId): self
    {
        $this->ownerType = $ownerType;
        $this->ownerId = $ownerId;

        return $this;
    }

    public function branchId(int|string $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    /** Queue a credit (balance increase). */
    public function credit(int|float $amount, ?string $description = null, string $type = 'credit'): self
    {
        $this->amount = $amount;
        $this->sign = 1;
        $this->type = $type;
        $this->description = $description;

        return $this;
    }

    /** Queue a debit (balance decrease). */
    public function debit(int|float $amount, ?string $description = null, string $type = 'debit'): self
    {
        $this->amount = $amount;
        $this->sign = -1;
        $this->type = $type;
        $this->description = $description;

        return $this;
    }

    /** Optional polymorphic link to the record that caused this movement (e.g. an OrderReturn). */
    public function transactionable(Model $model): self
    {
        $this->transactionable = $model;

        return $this;
    }

    /** Optional DB-unique key for safe retries of save(). */
    public function idempotencyKey(string $key): self
    {
        $this->idempotencyKey = $key;

        return $this;
    }

    /**
     * Persist the queued credit/debit. Retry-safe when idempotencyKey() is
     * set: a second call with the same key and payload returns the same
     * WalletTransaction without moving the balance again.
     */
    public function save(): WalletTransaction
    {
        if ($this->ownerId === null || $this->ownerType === null || $this->branchId === null || $this->amount === null) {
            throw new InvalidArgumentException('WalletBuilder::save() requires forUser()/for(), branchId(), and credit()/debit() before use.');
        }

        $arguments = [
            'ownerId' => $this->ownerId,
            'branchId' => $this->branchId,
            'amount' => $this->amount,
            'description' => $this->description,
            'transactionable' => $this->transactionable,
            'idempotencyKey' => $this->idempotencyKey,
            'ownerType' => $this->ownerType,
            'type' => $this->type,
        ];

        return $this->sign === 1
            ? $this->walletService->credit(...$arguments)
            : $this->walletService->debit(...$arguments);
    }
}
