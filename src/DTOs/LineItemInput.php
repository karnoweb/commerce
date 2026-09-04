<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

/**
 * Normalized input for CartService::addLine() — the one generic shape
 * behind every cart line, no matter what it represents. `itemType` is a
 * free-form string the host defines ('shop.product', 'custom.service',
 * 'custom.text', 'module.warranty', ...); `itemId` is a soft, nullable
 * reference (no FK); `itemName` is a required snapshot so a line never
 * depends on the referenced record still existing. `lineTotalAmount` is
 * always `quantity x unitPrice` — there is no per-line tax/discount; any
 * breakdown lives at the order level via `document_adjustments`.
 * `dimensions` are written as `document_dimensions` rows against the
 * created line (e.g. `['sales_unit_id' => 5, 'channel_id' => 2]`).
 */
final readonly class LineItemInput
{
    /**
     * @param  array<string, mixed>  $extra  Arbitrary snapshot data.
     * @param  array<string, mixed>  $dimensions  Reporting dimensions written as document_dimensions rows.
     */
    public function __construct(
        public string $itemType,
        public string $itemName,
        public int|float $quantity,
        public int|float $unitPrice,
        public int|string|null $itemId = null,
        public ?string $itemSku = null,
        public ?string $uomCode = null,
        public int|string|null $branchId = null,
        public ?string $expiresAt = null,
        public array $extra = [],
        public array $dimensions = [],
    ) {}
}
