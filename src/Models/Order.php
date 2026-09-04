<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\OrderTypeEnum;

class Order extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'idempotency_key',
        'user_id',
        'branch_id',
        'deal_id',
        'total',
        'subtotal',
        'discount_amount',
        'tax_amount',
        'shipping_amount',
        'pure_amount',
        'total_amount',
        'address',
        'description',
        'payment_method_id',
        'shipping_method_id',
        'address_id',
        'discount_id',
        'campaign_id',
        'note',
        'status',
        'type',
        'date',
        'order_date',
        'expired_at',
        'paid_at',
        'cancel_at',
        'cancel_by',
        'delivered_at',
        'delivered_by',
        'created_by',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'datetime',
            'order_date' => 'date',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancel_at' => 'datetime',
            'delivered_at' => 'datetime',
            'address' => 'array',
            'type' => OrderTypeEnum::class,
            'status' => OrderStatusEnum::class,
            'extra_attributes' => 'array',
            'subtotal' => 'decimal:0',
            'discount_amount' => 'decimal:0',
            'tax_amount' => 'decimal:0',
            'shipping_amount' => 'decimal:0',
            'pure_amount' => 'decimal:0',
            'total_amount' => 'decimal:0',
            'total' => 'decimal:0',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(config('commerce.models.order_item', OrderItem::class));
    }

    public function returns(): HasMany
    {
        return $this->hasMany(config('commerce.models.order_return', OrderReturn::class));
    }

    public function totals(): HasMany
    {
        return $this->hasMany(config('commerce.models.order_total', OrderTotal::class));
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(config('commerce.models.invoice', Invoice::class));
    }

    public function payments(): HasMany
    {
        return $this->hasMany(config('commerce.models.payment', Payment::class));
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(config('commerce.models.transaction', Transaction::class));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.user'));
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.discount', Discount::class));
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.campaign'));
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.payment_method', PaymentMethod::class));
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.shipping_method', ShippingMethod::class));
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.address'), 'address_id');
    }
}
