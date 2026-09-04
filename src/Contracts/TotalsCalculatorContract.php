<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Contracts;

use Illuminate\Support\Collection;
use Karnoweb\Commerce\Models\OrderItem;

/**
 * Extension point for combining cart lines with the resolved shipping/tax/
 * discount amounts into the final order totals snapshot. Commerce ships a
 * default (`DefaultTotalsCalculator`): subtotal = sum(line sale_price *
 * quantity), total = subtotal - discount + tax + shipping. Hosts that need
 * different rounding or composition rules bind their own implementation:
 *
 *   $this->app->bind(TotalsCalculatorContract::class, MyTotalsCalculator::class);
 */
interface TotalsCalculatorContract
{
    /**
     * @param  Collection<int, OrderItem>  $items  Cart lines being checked out.
     * @param  array{shipping_amount: int|float, tax_amount: int|float, discount_amount: int|float}  $context
     * @return array{subtotal: int|float, discount_amount: int|float, tax_amount: int|float, shipping_amount: int|float, total: int|float}
     */
    public function calculate(Collection $items, array $context): array;
}
