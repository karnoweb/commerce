<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderReturn extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'user_id',
        'amount',
        'tax_amount',
        'discount_amount',
        'reason',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'tax_amount' => 'float',
            'discount_amount' => 'float',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.user'));
    }
}
