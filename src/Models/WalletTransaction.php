<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletTransaction extends BaseModel
{
    protected $fillable = [
        'transaction_id',
        'causer_id',
        'wallet_id',
        'amount',
        'sign',
        'type',
        'description',
        'reference',
        'idempotency_key',
        'published',
        'transactionable_id',
        'transactionable_type',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'sign' => 'integer',
            'published' => 'boolean',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.wallet', Wallet::class));
    }

    public function transactionable(): MorphTo
    {
        return $this->morphTo('transactionable', 'transactionable_type', 'transactionable_id');
    }
}
