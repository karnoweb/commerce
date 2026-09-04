<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Builders;

use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;
use Karnoweb\Commerce\DTOs\LineItemInput;
use Karnoweb\Commerce\Models\OrderLine;
use Karnoweb\Commerce\Services\CartService;

/**
 * Fluent entry point for building a user's cart. Every line is generic:
 * itemType (a free-form string key such as 'shop.product', 'custom.text',
 * 'custom.service', or anything the host defines) + an optional itemId +
 * a required itemName snapshot. There is no product_id anywhere, and no
 * per-line tax/discount column — lineTotal is simply quantity x unitPrice.
 *
 * @example
 * Commerce::cart()
 *     ->forUser($userId)
 *     ->branchId($branchId)
 *     ->salesUnitId($salesUnitId)
 *     ->warehouseId($warehouseId)
 *     ->addLine(itemType: 'shop.product', name: 'Coffee Beans 1kg', quantity: 2, unitPrice: 1_000_000, itemId: 501, sku: 'COF-1KG', uomCode: 'kg')
 *     ->addLine(itemType: 'custom.text', name: 'Special packaging', quantity: 1, unitPrice: 50_000);
 */
class CartBuilder
{
    private int|string|null $userId = null;

    private int|string|null $branchId = null;

    /** @var array<string, mixed> Reporting dimensions (document_dimensions rows) applied to every line added afterward. */
    private array $dimensions = [];

    public function __construct(private readonly CartService $cartService) {}

    /** Set the cart owner. Required before addLine()/items()/clear(). */
    public function forUser(int|string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    /** Optional branch context, applied to every line added afterward. */
    public function branchId(int|string $branchId): self
    {
        $this->branchId = $branchId;

        return $this;
    }

    /** Reporting dimension (which sales unit sold this) — writes a document_dimensions row for every line added afterward. */
    public function salesUnitId(int|string $salesUnitId): self
    {
        $this->dimensions['sales_unit_id'] = $salesUnitId;

        return $this;
    }

    /** Reporting dimension (which warehouse is involved) — writes a document_dimensions row for every line added afterward. */
    public function warehouseId(int|string $warehouseId): self
    {
        $this->dimensions['warehouse_id'] = $warehouseId;

        return $this;
    }

    /** Arbitrary reporting dimension (region_id, channel_id, cashier_id, ...), applied to every line added afterward. */
    public function addDimension(string $key, mixed $value): self
    {
        $this->dimensions[$key] = $value;

        return $this;
    }

    /**
     * Add a generic line to the cart (an OrderLine with order_id = null).
     * $itemType is a free-form string the host defines; $itemId is a soft,
     * nullable reference (no FK) — $name is the required snapshot.
     *
     * @param  array<string, mixed>  $extra  Arbitrary snapshot data.
     */
    public function addLine(
        string $itemType,
        string $name,
        int|float $quantity,
        int|float $unitPrice,
        int|string|null $itemId = null,
        ?string $sku = null,
        ?string $uomCode = null,
        array $extra = [],
        ?string $expiresAt = null,
    ): self {
        $this->assertUser();

        $this->cartService->addLine($this->userId, new LineItemInput(
            itemType: $itemType,
            itemName: $name,
            quantity: $quantity,
            unitPrice: $unitPrice,
            itemId: $itemId,
            itemSku: $sku,
            uomCode: $uomCode,
            branchId: $this->branchId,
            expiresAt: $expiresAt,
            extra: $extra,
            dimensions: $this->dimensions,
        ));

        return $this;
    }

    /**
     * @deprecated Prefer addLine(itemType: 'shop.product', ...). Kept as a
     *             thin alias — calls addLine() under the hood.
     *
     * @param  array<string, mixed>  $extra
     */
    public function addProductItem(
        int|string $itemId,
        string $name,
        int|float $quantity,
        int|float $unitPrice,
        ?string $sku = null,
        array $extra = [],
    ): self {
        return $this->addLine(
            itemType: 'shop.product',
            name: $name,
            quantity: $quantity,
            unitPrice: $unitPrice,
            itemId: $itemId,
            sku: $sku,
            extra: $extra,
        );
    }

    /**
     * @deprecated Prefer addLine(itemType: 'custom.service', ...). Kept as
     *             a thin alias — calls addLine() under the hood.
     *
     * @param  array<string, mixed>  $extra
     */
    public function addServiceItem(string $name, int|float $quantity, int|float $unitPrice, array $extra = []): self
    {
        return $this->addLine(itemType: 'custom.service', name: $name, quantity: $quantity, unitPrice: $unitPrice, extra: $extra);
    }

    /**
     * @deprecated Prefer addLine(itemType: 'custom.text', ...). Kept as a
     *             thin alias — calls addLine() under the hood. Quantity
     *             defaults to 1 and unitPrice defaults to $amount.
     *
     * @param  array<string, mixed>  $extra
     */
    public function addTextItem(string $description, int|float $amount, array $extra = []): self
    {
        return $this->addLine(itemType: 'custom.text', name: $description, quantity: 1, unitPrice: $amount, extra: $extra);
    }

    /**
     * @deprecated Prefer addLine(itemType: '<your own key>', ...). Kept as
     *             a thin alias — calls addLine() under the hood.
     *
     * @param  array<string, mixed>  $extra
     */
    public function addCustomItem(
        ?string $itemType,
        int|string|null $itemId,
        string $name,
        int|float $quantity,
        int|float $unitPrice,
        array $extra = [],
    ): self {
        return $this->addLine(
            itemType: $itemType ?? 'custom.item',
            name: $name,
            quantity: $quantity,
            unitPrice: $unitPrice,
            itemId: $itemId,
            extra: $extra,
        );
    }

    /**
     * @return Collection<int, OrderLine>
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
