<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Contracts;

use Illuminate\Support\Collection;
use Karnoweb\Commerce\Models\OrderItem;

/**
 * Extension point for order-level tax calculation. Commerce ships a no-op
 * default (0) — hosts that need real tax rules (VAT, per-branch rates, ...)
 * bind their own implementation in their own service provider:
 *
 *   $this->app->bind(TaxCalculatorContract::class, MyTaxCalculator::class);
 *
 * Only invoked by CheckoutService when the caller does not pass an explicit
 * `taxAmount()` to Commerce::checkout() — an explicit amount always wins.
 */
interface TaxCalculatorContract
{
    /**
     * @param  Collection<int, OrderItem>  $items  Cart lines being checked out.
     * @param  array<string, mixed>  $context  Order-level context (user_id, branch_id, subtotal, shipping_amount, discount_amount, ...).
     */
    public function calculate(Collection $items, array $context): int|float;
}
