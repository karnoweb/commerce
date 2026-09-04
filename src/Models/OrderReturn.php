<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderReturn extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'idempotency_key',
        'total_amount',
        'reason',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'integer',
            'extra_attributes' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class));
    }

    /**
     * Quantity-based return lines created via ReturnService — empty for a
     * legacy amount-only refund created via RefundService.
     */
    public function lines(): HasMany
    {
        return $this->hasMany(config('commerce.models.order_return_line', OrderReturnLine::class));
    }
}
