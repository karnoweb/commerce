<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Karnoweb\Commerce\Models\Concerns\HasAdjustments;

/**
 * A flexible +/- amount against any document this package owns (`Order`,
 * `Invoice`, ...), keyed by a free-form string (`shipping`, `tax`,
 * `discount`, `rounding`, `fee`, `coupon`, a host's own key, ...).
 * `amount` is always the unsigned magnitude; `sign` (+1/-1) carries the
 * direction. This table replaces fixed `discount_amount`/`tax_amount`/
 * `shipping_amount` columns on `orders`/`invoices` — it is the single
 * source of truth for a document's total breakdown (see
 * {@see HasAdjustments}).
 */
class DocumentAdjustment extends BaseModel
{
    protected $fillable = [
        'adjustable_type',
        'adjustable_id',
        'key',
        'sign',
        'amount',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'sign' => 'integer',
            'amount' => 'integer',
            'payload' => 'array',
        ];
    }

    public function adjustable(): MorphTo
    {
        return $this->morphTo();
    }
}
