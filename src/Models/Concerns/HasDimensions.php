<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Karnoweb\Commerce\Models\DocumentDimension;

/**
 * Adds a polymorphic `document_dimensions` ledger to any model this
 * package owns (`Order`, `Invoice`, `OrderLine`, ...) for generic
 * reporting/filtering dimensions (sales unit, warehouse, channel,
 * cashier, ...) with zero schema changes — see COMMERCE_PACKAGE.md.
 */
trait HasDimensions
{
    public function dimensions(): MorphMany
    {
        return $this->morphMany(config('commerce.models.document_dimension', DocumentDimension::class), 'documentable');
    }

    public function addDimension(string $key, mixed $value): DocumentDimension
    {
        return $this->dimensions()->create([
            'key' => $key,
            'value_int' => is_numeric($value) ? (int) $value : null,
            'value_string' => is_string($value) && ! is_numeric($value) ? $value : null,
            'value_json' => is_array($value) ? $value : null,
        ]);
    }

    public function dimensionValue(string $key): mixed
    {
        $dimension = $this->dimensions->firstWhere('key', $key);

        if ($dimension === null) {
            return null;
        }

        return $dimension->value_int ?? $dimension->value_string ?? $dimension->value_json;
    }
}
