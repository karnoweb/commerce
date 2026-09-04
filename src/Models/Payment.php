<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;

/**
 * A payment always settles an Invoice (`invoice_id` required) — this is the
 * authoritative billing link. `order_id` is a denormalized convenience
 * lookup only (nullable; null for payments against a standalone invoice).
 */
class Payment extends BaseModel
{
    protected $fillable = [
        'idempotency_key',
        'invoice_id',
        'order_id',
        'user_id',
        'branch_id',
        'payment_method_id',
        'amount',
        'type',
        'status',
        'note',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'type' => PaymentTypeEnum::class,
            'status' => PaymentStatusEnum::class,
            'amount' => 'integer',
            'extra_attributes' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.invoice', Invoice::class));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class));
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.payment_method', PaymentMethod::class));
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(config('commerce.models.transaction', Transaction::class));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.user'));
    }
}
