<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A normalized, package-owned catalog of return reasons (`damaged`,
 * `wrong_item`, `not_needed`, `other`, ...) so analytics can group returns
 * by `return_reason_id` instead of parsing free-form strings. Seed the
 * defaults with `php artisan db:seed --class="Karnoweb\\Commerce\\Database\\Seeders\\CommerceSeeder"`.
 */
class ReturnReason extends BaseModel
{
    protected $fillable = [
        'code',
        'title',
        'published',
        'ordering',
    ];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'ordering' => 'integer',
        ];
    }

    public function returnLines(): HasMany
    {
        return $this->hasMany(config('commerce.models.order_return_line', OrderReturnLine::class));
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('published', true);
    }
}
