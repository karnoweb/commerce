<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Karnoweb\Commerce\Models\DocumentAdjustment;

/**
 * Adds a polymorphic `document_adjustments` ledger to any model that can
 * carry a total (`Order`, `Invoice`, ...). `shippingAmount()`/`taxAmount()`/
 * `discountAmount()` are read-only convenience accessors over the ledger —
 * there are no fixed columns for these on the owning table; the ledger is
 * the single source of truth (see COMMERCE_PACKAGE.md).
 */
trait HasAdjustments
{
    public function adjustments(): MorphMany
    {
        return $this->morphMany(config('commerce.models.document_adjustment', DocumentAdjustment::class), 'adjustable');
    }

    /**
     * Signed sum (`sign` x `amount`) of every adjustment row for a given key.
     */
    public function adjustmentAmount(string $key): int
    {
        return (int) $this->adjustments
            ->where('key', $key)
            ->sum(static fn (DocumentAdjustment $adjustment): int => $adjustment->sign * $adjustment->amount);
    }

    public function shippingAmount(): int
    {
        return $this->adjustmentAmount('shipping');
    }

    public function taxAmount(): int
    {
        return $this->adjustmentAmount('tax');
    }

    public function discountAmount(): int
    {
        return abs($this->adjustmentAmount('discount'));
    }
}
