<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Karnoweb\Commerce\DTOs\CartItemInput;
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
