<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Karnoweb\Commerce\Enums\PaymentStatusEnum;
use Karnoweb\Commerce\Enums\PaymentTypeEnum;

class Payment extends BaseModel
{
    protected $fillable = [
        'user_id',
        'branch_id',
        'order_id',
        'invoice_id',
        'payment_method_id',
        'amount',
        'scheduled_date',
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
            'amount' => 'float',
            'scheduled_date' => 'date',
            'extra_attributes' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class));
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.invoice', Invoice::class));
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
