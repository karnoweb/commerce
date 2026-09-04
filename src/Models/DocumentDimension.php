<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Karnoweb\Commerce\Models\Concerns\HasDimensions;

/**
 * A generic reporting/filtering dimension against any document this
 * package owns (`Order`, `Invoice`, `OrderLine`, `Payment`, ...), keyed by
 * a free-form string (`sales_unit_id`, `warehouse_id`, `channel_id`,
 * `cashier_id`, a host's own key, ...). Written automatically by the
 * `salesUnitId()`/`warehouseId()` shortcuts and by `addDimension()` for
 * arbitrary keys — see {@see HasDimensions}
 * and COMMERCE_PACKAGE.md for real filtering examples.
 */
class DocumentDimension extends BaseModel
{
    protected $fillable = [
        'documentable_type',
        'documentable_id',
        'key',
        'value_int',
        'value_string',
        'value_json',
    ];

    protected function casts(): array
    {
        return [
            'value_int' => 'integer',
            'value_json' => 'array',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeForKey(Builder $query, string $key): Builder
    {
        return $query->where('key', $key);
    }

    public function scopeValueIn(Builder $query, array $values): Builder
    {
        return $query->whereIn('value_int', $values);
    }
}
