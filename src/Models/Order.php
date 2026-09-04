<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Commerce\Enums\OrderStatusEnum;
use Karnoweb\Commerce\Enums\OrderTypeEnum;
use Karnoweb\Commerce\Models\Concerns\HasAdjustments;
use Karnoweb\Commerce\Models\Concerns\HasDimensions;

/**
 * `subtotal_amount`/`total_amount` are the only stored amount columns —
 * there is no `discount_amount`/`tax_amount`/`shipping_amount` column.
 * Those live exclusively in the polymorphic `document_adjustments` ledger
 * (see {@see HasAdjustments}); `shippingAmount()`/`taxAmount()`/
 * `discountAmount()` below are computed accessors, not DB columns.
 */
class Order extends BaseModel
{
    use HasAdjustments;
    use HasDimensions;
    use SoftDeletes;

    protected $fillable = [
        'idempotency_key',
        'order_number',
        'user_id',
        'branch_id',
        'sales_unit_id',
        'warehouse_id',
        'type',
        'status',
        'subtotal_amount',
        'total_amount',
        'currency',
        'note',
        'paid_at',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrderTypeEnum::class,
            'status' => OrderStatusEnum::class,
            'subtotal_amount' => 'integer',
            'total_amount' => 'integer',
            'paid_at' => 'datetime',
            'extra_attributes' => 'array',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(config('commerce.models.order_line', OrderLine::class));
    }

    public function returns(): HasMany
    {
        return $this->hasMany(config('commerce.models.order_return', OrderReturn::class));
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(config('commerce.models.invoice', Invoice::class));
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
