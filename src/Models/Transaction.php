<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single gateway outcome reported by the host against a Payment
 * (`payment_id` required). Commerce never calls a gateway itself — this
 * row only records the result the host already obtained.
 */
class Transaction extends BaseModel
{
    protected $fillable = [
        'payment_id',
        'gateway',
        'ref_id',
        'tracking_code',
        'gateway_response',
        'paid_at',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'gateway_response' => 'array',
            'paid_at' => 'immutable_datetime',
            'extra_attributes' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.payment', Payment::class));
    }
}
