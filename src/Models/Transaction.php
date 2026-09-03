<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'payment_method_id',
        'order_id',
        'type',
        'amount',
        'status',
        'authority',
        'ref_id',
        'tracking_code',
        'card_number',
        'gateway_response',
        'paid_at',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'paid_at' => 'immutable_datetime',
            'gateway_response' => 'array',
            'extra_attributes' => 'array',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class));
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.payment_method', PaymentMethod::class));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.user'));
    }
}
