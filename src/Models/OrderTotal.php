<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderTotal extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'order_id',
        'type',
        'sign',
        'price',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'price' => 'decimal:0',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class), 'order_id');
    }
}
