<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends BaseModel
{
    protected $fillable = [
        'invoice_number',
        'branch_id',
        'user_id',
        'order_id',
        'amount',
        'tax_amount',
        'discount_amount',
        'invoice_date',
        'status',
        'note',
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'tax_amount' => 'float',
            'discount_amount' => 'float',
            'invoice_date' => 'date',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order', Order::class));
    }

    public function payments(): HasMany
    {
        return $this->hasMany(config('commerce.models.payment', Payment::class));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.user'));
    }
}
