<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Karnoweb\Commerce\Enums\LineItemTypeEnum;

class OrderItem extends BaseModel
{
    protected $fillable = [
        'order_id',
        'user_id',
        'product_id',
        'campaign_id',
        'item_type',
        'title',
        'itemable_type',
        'itemable_id',
        'price',
        'unit_cost',
        'base_price',
        'sale_price',
        'quantity',
        'discount_amount',
        'tax_amount',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'item_type' => LineItemTypeEnum::class,
            'price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'base_price' => 'decimal:0',
            'sale_price' => 'decimal:0',
            'discount_amount' => 'decimal:0',
            'tax_amount' => 'decimal:0',
            'extra_attributes' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderItem $item): void {
            if ($item->product_id && ! $item->itemable_type) {
                $productModel = (string) (config('commerce.models.product') ?: config('shop.models.product'));
                $item->itemable_type = $productModel;
                $item->itemable_id = $item->product_id;
            }

            if ($item->sale_price !== null && $item->price === null) {
                $item->price = $item->sale_price;
            }

            if ($item->price !== null && $item->sale_price === null) {
                $item->sale_price = $item->price;
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.user'));
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.product'));
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.campaign'));
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeCarts(Builder $query): Builder
    {
        return $query->whereNull('order_id');
    }

    public function scopeByOrder(Builder $query, int $orderId): Builder
    {
        return $query->where('order_id', $orderId);
    }

    public function getTotalAttribute(): float
    {
        $unitPrice = (float) ($this->sale_price ?? $this->price);

        return ($unitPrice * $this->quantity) - (float) $this->discount_amount;
    }

    public function getTotalPriceAttribute(): float
    {
        return (float) ($this->price ?? $this->sale_price) * $this->quantity;
    }

    public function getFinalUnitPriceAttribute(): float
    {
        $unitPrice = (float) ($this->sale_price ?? $this->price);

        if ($this->quantity < 1) {
            return $unitPrice;
        }

        return max(0, $unitPrice - ((float) $this->discount_amount / $this->quantity));
    }
}
