<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Commerce\Enums\DiscountTypeEnum;

class Discount extends BaseModel
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'user_id',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit',
        'usage_per_user',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountTypeEnum::class,
            'value' => 'float',
            'min_order_amount' => 'float',
            'max_discount_amount' => 'float',
            'usage_limit' => 'integer',
            'usage_per_user' => 'integer',
            'used_count' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.user'));
    }
}
