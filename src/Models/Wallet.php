<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'reference_type',
        'reference_id',
        'branch_id',
        'primary',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'primary' => 'boolean',
            'extra_attributes' => 'array',
        ];
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(config('commerce.models.wallet_transaction', WalletTransaction::class));
    }
}
