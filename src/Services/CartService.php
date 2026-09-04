<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Services;

use Illuminate\Database\Eloquent\Collection;
use Karnoweb\Commerce\DTOs\LineItemInput;
use Karnoweb\Commerce\Models\OrderLine;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Package-safe cart persistence: an OrderLine with a null order_id is a
 * cart line. No session/auth helpers, no HTTP access, no shop model
 * dependency — every line is a generic item_type/item_id/item_name
 * reference. There is no `product_id` anywhere in this schema, and no
 * per-line tax/discount column: `line_total_amount` is simply
 * `quantity x unit_price_amount`.
 */
class CartService
{
    use ResolvesConfiguredModels;

    /**
     * Add a generic line (product/service/text/custom/anything the host
     * defines) to the user's active cart. Backs CartBuilder::addLine() and
     * its addProductItem()/addServiceItem()/addTextItem()/addCustomItem()
     * deprecated aliases.
     */
    public function addLine(int|string $userId, LineItemInput $input): OrderLine
    {
        $quantity = (float) $input->quantity;
        $unitPriceAmount = (int) round($input->unitPrice);
        $lineTotalAmount = (int) round($unitPriceAmount * $quantity);

        $class = static::model('order_line');

        /** @var OrderLine $line */
        $line = $class::create([
            'user_id' => $userId,
            'branch_id' => $input->branchId,
            'item_type' => $input->itemType,
            'item_id' => $input->itemId,
            'item_name' => $input->itemName,
            'item_sku' => $input->itemSku,
            'quantity' => $quantity,
            'uom_code' => $input->uomCode,
            'unit_price_amount' => $unitPriceAmount,
            'line_total_amount' => $lineTotalAmount,
            'expires_at' => $input->expiresAt,
            'extra_attributes' => $input->extra === [] ? null : $input->extra,
        ]);

        foreach ($input->dimensions as $key => $value) {
            $line->addDimension($key, $value);
        }

        return $line;
    }

    /**
     * Cart lines for a user (order_id is null), oldest first.
     *
     * @return Collection<int, OrderLine>
     */
    public function items(int|string $userId): Collection
    {
        $class = static::model('order_line');

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
        $class = static::model('order_line');

        return $class::query()
            ->carts()
            ->where('user_id', $userId)
            ->delete();
    }
}
