<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Commerce\Models\Concerns\HasDimensions;

/**
 * A generic cart/order line: `item_type` + `item_id` (soft, nullable) is a
 * pointer to whatever the host wants to sell (catalog product, service,
 * module, warranty, ...); `item_name`/`item_sku` are required/optional
 * snapshots so a line never depends on the referenced record still
 * existing. `order_id = null` means the line is still sitting in a cart.
 * `line_total_amount` is simply `quantity x unit_price_amount` — any
 * tax/discount/fee lives exclusively at the order level via
 * `document_adjustments` (see COMMERCE_PACKAGE.md). Reporting dimensions
 * (sales unit, warehouse, ...) can be attached per line via
 * {@see HasDimensions} instead of dedicated columns.
 */
class OrderLine extends BaseModel
{
    use HasDimensions;

    protected $fillable = [
        'order_id',
        'user_id',
        'branch_id',
        'item_type',
        'item_id',
        'item_name',
        'item_sku',
        'quantity',
        'uom_code',
        'unit_price_amount',
        'line_total_amount',
        'expires_at',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_price_amount' => 'integer',
            'line_total_amount' => 'integer',
            'expires_at' => 'date',
            'extra_attributes' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class));
    }

    public function returnLines(): HasMany
    {
        return $this->hasMany(config('commerce.models.order_return_line', OrderReturnLine::class));
    }

    public function scopeCarts(Builder $query): Builder
    {
        return $query->whereNull('order_id');
    }

    public function scopeByOrder(Builder $query, int|string $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }
}
