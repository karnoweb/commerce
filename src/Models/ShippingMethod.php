<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Translation\Concerns\HasTranslation;

/**
 * @property string|null $title
 * @property string|null $description
 */
class ShippingMethod extends BaseModel
{
    use HasTranslation;
    use SoftDeletes;

    /** @var list<string> */
    protected array $translatable = [
        'title',
        'description',
    ];

    protected $fillable = [
        'published',
        'driver',
        'price',
        'free_threshold',
        'min_order_amount',
        'max_weight',
        'estimated_days',
        'ordering',
        'extra_attributes',
        'languages',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'price' => 'integer',
            'free_threshold' => 'integer',
            'min_order_amount' => 'integer',
            'max_weight' => 'decimal:2',
            'estimated_days' => 'integer',
            'ordering' => 'integer',
            'extra_attributes' => 'array',
            'languages' => 'array',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('published', true);
    }
}
