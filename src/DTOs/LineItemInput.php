<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

use Karnoweb\Commerce\Enums\LineItemTypeEnum;

/**
 * Normalized input for CartService::addLine() — the generic counterpart of
 * CartItemInput that backs product/service/text/custom lines. `productId`
 * stays a soft catalog reference; `itemableType`/`itemableId` are an
 * optional host-defined polymorphic reference for `custom` lines only.
 */
final readonly class LineItemInput
{
    /**
     * @param  array<string, mixed>  $extra  Arbitrary snapshot data (sku, price_source, ...).
     */
    public function __construct(
        public LineItemTypeEnum $type,
        public int $quantity,
        public int|float $unitPrice,
        public ?string $title = null,
        public int|string|null $productId = null,
        public ?string $itemableType = null,
        public int|string|null $itemableId = null,
        public array $extra = [],
        public int|string|null $campaignId = null,
        public int|float $taxAmount = 0,
        public int|float $discountAmount = 0,
    ) {}
}
