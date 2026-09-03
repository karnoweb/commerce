<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends BaseModel
{
    use SoftDeletes;

    protected $fillable = [
        'provider',
        'extra_attributes',
        'published',
        'ordering',
        'languages',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'extra_attributes' => 'array',
            'languages' => 'array',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(config('commerce.models.order', Order::class));
    }

    public function payments(): HasMany
    {
        return $this->hasMany(config('commerce.models.payment', Payment::class));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('published', true);
    }
}
