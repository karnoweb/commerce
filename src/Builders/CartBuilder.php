<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Karnoweb\Commerce\DTOs\CartItemInput;
use Karnoweb\Commerce\DTOs\LineItemInput;
use Karnoweb\Commerce\Enums\LineItemTypeEnum;
use Karnoweb\Commerce\Models\OrderItem;
use Karnoweb\Commerce\Services\CartService;

/**
 * Fluent entry point for building a user's cart.
 *
 * @example
 * Commerce::cart()
 *     ->forUser($userId)
 *     ->branchId($branchId)
 *     ->addItem(productId: 501, quantity: 2, unitPrice: 1_000_000, extra: [...]);
 */
class CartBuilder
{
    private int|string|null $userId = null;

    private int|string|null $branchId = null;

    public function __construct(private readonly CartService $cartService) {}

    /** Set the cart owner. Required before addItem()/items()/clear(). */
    public function forUser(int|string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /** Optional branch context, snapshotted onto each added item's extra_attributes. */
    public function branchId(int|string $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    /**
     * Add a line to the cart (an OrderItem with order_id = null). $productId
     * is a soft reference to the product catalog — no shop model dependency.
     *
     * @param  array<string, mixed>  $extra  Arbitrary snapshot (title, sku, price_source, ...).
     */
    public function addItem(
        int|string $productId,
        int $quantity,
        int|float $unitPrice,
        array $extra = [],
        int|string|null $campaignId = null,
        int|float $taxAmount = 0,
        int|float $discountAmount = 0,
    ): self {
        $this->assertUser();

        $this->cartService->addItem(
            $this->userId,
            new CartItemInput(
                productId: $productId,
                quantity: $quantity,
                unitPrice: $unitPrice,
                extra: $extra,
                campaignId: $campaignId,
                taxAmount: $taxAmount,
                discountAmount: $discountAmount,
            ),
            $this->branchId,
        );

        return $this;
    }

    /**
     * Add a product line to the cart — a soft reference to the catalog
     * (no shop model dependency). $sku/$title are convenience overrides
     * merged on top of $extra; equivalent to putting 'sku'/'title' keys
     * directly in $extra.
     *
     * @param  array<string, mixed>  $extra  Arbitrary snapshot (price_source, ...).
     */
    public function addProductItem(
        int|string $productId,
        int $quantity,
        int|float $unitPrice,
        array $extra = [],
        ?string $sku = null,
        ?string $title = null,
    ): self {
        $this->assertUser();

        if ($sku !== null) {
            $extra['sku'] = $sku;
        }

        $this->cartService->addLine(
            $this->userId,
            new LineItemInput(
                type: LineItemTypeEnum::PRODUCT,
                quantity: $quantity,
                unitPrice: $unitPrice,
                title: $title,
                productId: $productId,
                extra: $extra,
            ),
            $this->branchId,
        );

        return $this;
    }

    /**
     * Add a catalog-free service line (installation, labor, ...): just a
     * title, quantity, and unit price — no product_id.
     *
     * @param  array<string, mixed>  $extra
     */
    public function addServiceItem(string $title, int $quantity, int|float $unitPrice, array $extra = []): self
    {
        $this->assertUser();

        $this->cartService->addLine(
            $this->userId,
            new LineItemInput(
                type: LineItemTypeEnum::SERVICE,
                quantity: $quantity,
                unitPrice: $unitPrice,
                title: $title,
                extra: $extra,
            ),
            $this->branchId,
        );

        return $this;
    }

    /**
     * Add a catalog-free text/fee line (packaging, glass breakage, ...).
     * Quantity defaults to 1 and unitPrice defaults to $amount.
     *
     * @param  array<string, mixed>  $extra
     */
    public function addTextItem(string $description, int|float $amount, array $extra = []): self
    {
        $this->assertUser();

        $this->cartService->addLine(
            $this->userId,
            new LineItemInput(
                type: LineItemTypeEnum::TEXT,
                quantity: 1,
                unitPrice: $amount,
                title: $description,
                extra: $extra,
            ),
            $this->branchId,
        );

        return $this;
    }

    /**
     * Add a host-defined polymorphic line via an optional
     * itemableType/itemableId pair (e.g. a bundle, a gift card, a
     * warranty add-on) — no product_id, no shop model dependency.
     *
     * @param  array<string, mixed>  $extra
     */
    public function addCustomItem(
        ?string $itemableType,
        int|string|null $itemableId,
        string $title,
        int $quantity,
        int|float $unitPrice,
        array $extra = [],
    ): self {
        $this->assertUser();

        $this->cartService->addLine(
            $this->userId,
            new LineItemInput(
                type: LineItemTypeEnum::CUSTOM,
                quantity: $quantity,
                unitPrice: $unitPrice,
                title: $title,
                itemableType: $itemableType,
                itemableId: $itemableId,
                extra: $extra,
            ),
            $this->branchId,
        );

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function items(): Collection
    {
        $this->assertUser();

        return $this->cartService->items($this->userId);
    }

    /** Remove all cart lines for this user. Returns the number of rows deleted. */
    public function clear(): int
    {
        $this->assertUser();

        return $this->cartService->clear($this->userId);
    }

    private function assertUser(): void
    {
        if ($this->userId === null) {
            throw new InvalidArgumentException('CartBuilder requires forUser() before use.');
        }
    }
}
