<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

/**
 * Normalized input for CartService::addItem(). Soft reference to the
 * product catalog via `productId` only — never a shop model instance.
 */
final readonly class CartItemInput
{
    /**
     * @param  array<string, mixed>  $extra  Arbitrary snapshot data (title, sku, price_source, ...).
     */
    public function __construct(
        public int|string $productId,
        public int $quantity,
        public int|float $unitPrice,
        public array $extra = [],
        public int|string|null $campaignId = null,
        public int|float $taxAmount = 0,
        public int|float $discountAmount = 0,
    ) {}
}
