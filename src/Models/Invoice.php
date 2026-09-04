<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Karnoweb\Commerce\Models\Concerns\HasAdjustments;
use Karnoweb\Commerce\Models\Concerns\HasDimensions;

/**
 * Invoices are the mandatory billing document: CheckoutService always
 * creates one for a placed order, but `order_id` stays nullable so a host
 * can also issue a standalone invoice (InvoiceService::issueStandalone(),
 * Commerce::invoices()->issueStandalone()) with no order at all. `amount`
 * is the only stored total — no `tax_amount`/`discount_amount` column;
 * any breakdown lives in the polymorphic `document_adjustments` ledger
 * (see {@see HasAdjustments}).
 */
class Invoice extends BaseModel
{
    use HasAdjustments;
    use HasDimensions;
    use SoftDeletes;

    protected $fillable = [
        'invoice_number',
        'idempotency_key',
        'order_id',
        'user_id',
        'branch_id',
        'sales_unit_id',
        'warehouse_id',
        'amount',
        'invoice_date',
        'status',
        'extra_attributes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'invoice_date' => 'date',
            'extra_attributes' => 'array',
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
