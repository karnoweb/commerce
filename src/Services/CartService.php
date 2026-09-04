<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Database\Eloquent\Collection;
use Karnoweb\Commerce\DTOs\CartItemInput;
use Karnoweb\Commerce\DTOs\LineItemInput;
use Karnoweb\Commerce\Models\OrderItem;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Package-safe cart persistence: an OrderItem with a null order_id is a
 * cart line. No session/auth helpers, no HTTP access, no shop model dependency — only a
 * soft `product_id` reference.
 */
class CartService
{
    use ResolvesConfiguredModels;

    /**
     * Add a line to the user's active cart (an OrderItem with order_id = null).
     */
    public function addItem(int|string $userId, CartItemInput $input, int|string|null $branchId = null): OrderItem
    {
        $extra = $input->extra;

        if ($branchId !== null && ! array_key_exists('branch_id', $extra)) {
            $extra['branch_id'] = $branchId;
        }

        $class = static::model('order_item');

        return $class::create([
            'user_id' => $userId,
            'product_id' => $input->productId,
            'campaign_id' => $input->campaignId,
            'quantity' => $input->quantity,
            'base_price' => $input->unitPrice,
            'sale_price' => $input->unitPrice,
            'discount_amount' => $input->discountAmount,
            'tax_amount' => $input->taxAmount,
            'extra_attributes' => $extra === [] ? null : $extra,
        ]);
    }

    /**
     * Add a generic line (product/service/text/custom) to the user's active
     * cart. Backs CartBuilder::addProductItem()/addServiceItem()/
     * addTextItem()/addCustomItem() — the fluent, type-safe front door for
     * this method.
     */
    public function addLine(int|string $userId, LineItemInput $input, int|string|null $branchId = null): OrderItem
    {
        $extra = $input->extra;

        if ($branchId !== null && ! array_key_exists('branch_id', $extra)) {
            $extra['branch_id'] = $branchId;
        }

        $class = static::model('order_item');

        return $class::create([
            'user_id' => $userId,
            'product_id' => $input->productId,
            'campaign_id' => $input->campaignId,
            'item_type' => $input->type,
            'title' => $input->title,
            'itemable_type' => $input->itemableType,
            'itemable_id' => $input->itemableId,
            'quantity' => $input->quantity,
            'base_price' => $input->unitPrice,
            'sale_price' => $input->unitPrice,
            'discount_amount' => $input->discountAmount,
            'tax_amount' => $input->taxAmount,
            'extra_attributes' => $extra === [] ? null : $extra,
        ]);
    }

    /**
     * Cart lines for a user (order_id is null), oldest first.
     *
     * @return Collection<int, OrderItem>
     */
    public function items(int|string $userId): Collection
    {
        $class = static::model('order_item');

        return $class::query()
            ->carts()
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();
    }

    /**
     * Remove all cart lines for a user. Returns the number of rows deleted.
     */
    public function clear(int|string $userId): int
    {
        $class = static::model('order_item');

        return $class::query()
            ->carts()
            ->where('user_id', $userId)
            ->delete();
    }
}
