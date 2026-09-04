<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Contracts;

use Illuminate\Support\Collection;
use Karnoweb\Commerce\Models\OrderItem;

/**
 * Extension point for order-level discount calculation. Commerce ships a
 * no-op default (0) — hosts that need real discount rules (campaigns, user
 * groups, ...) bind their own implementation in their own service provider:
 *
 *   $this->app->bind(DiscountCalculatorContract::class, MyDiscountCalculator::class);
 *
 * Only invoked by CheckoutService when the caller does not pass an explicit
 * `discountAmount()` to Commerce::checkout() — an explicit amount always wins.
 */
interface DiscountCalculatorContract
{
    /**
     * @param  Collection<int, OrderItem>  $items  Cart lines being checked out.
     * @param  array<string, mixed>  $context  Order-level context (user_id, branch_id, subtotal, shipping_amount, tax_amount, ...).
     */
    public function calculate(Collection $items, array $context): int|float;
}
