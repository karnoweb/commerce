<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One returned quantity against an original sale line. `unit_price_snapshot`
 * and `amount` freeze the price at return time — never recomputed from the
 * live product price.
 */
class OrderReturnItem extends BaseModel
{
    protected $fillable = [
        'order_return_id',
        'order_item_id',
        'quantity',
        'unit_price_snapshot',
        'amount',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_snapshot' => 'decimal:0',
            'amount' => 'decimal:0',
        ];
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order_return', OrderReturn::class));
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order_item', OrderItem::class));
    }
}
