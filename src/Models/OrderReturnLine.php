<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One returned quantity against an original sale line (`OrderLine`).
 * `unit_price_amount`/`amount` are frozen at return time — they never
 * change even if the original line's price is edited later. `reason` is
 * normalized via `return_reason_id` (internal FK to `ReturnReason`,
 * seeded with damaged/wrong_item/not_needed/other); `reason_note` is an
 * optional free-text note alongside it.
 */
class OrderReturnLine extends BaseModel
{
    protected $fillable = [
        'order_return_id',
        'order_line_id',
        'quantity',
        'unit_price_amount',
        'amount',
        'return_reason_id',
        'reason_note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:6',
            'unit_price_amount' => 'integer',
            'amount' => 'integer',
        ];
    }

    public function orderReturn(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order_return', OrderReturn::class));
    }

    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.order_line', OrderLine::class));
    }

    public function returnReason(): BelongsTo
    {
        return $this->belongsTo(config('commerce.models.return_reason', ReturnReason::class));
    }
}
