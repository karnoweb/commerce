<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support\Calculators;

use Illuminate\Support\Collection;
use Karnoweb\Commerce\Contracts\TotalsCalculatorContract;

/**
 * Default totals calculator used by CheckoutService: subtotal is the sum of
 * each line's `sale_price * quantity`; line-level `discount_amount`/
 * `tax_amount` are folded into the order-level ones passed in `$context`;
 * total = subtotal - discount + tax + shipping. Bind
 * {@see TotalsCalculatorContract} to your own implementation to change this.
 */
final class DefaultTotalsCalculator implements TotalsCalculatorContract
{
    public function calculate(Collection $items, array $context): array
    {
        $subtotal = 0.0;
        $itemDiscount = 0.0;
        $itemTax = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) $item->sale_price * (int) $item->quantity;
            $itemDiscount += (float) $item->discount_amount;
            $itemTax += (float) $item->tax_amount;
        }

        $shippingAmount = (float) ($context['shipping_amount'] ?? 0);
        $discountAmount = $itemDiscount + (float) ($context['discount_amount'] ?? 0);
        $taxAmount = $itemTax + (float) ($context['tax_amount'] ?? 0);
        $total = $subtotal - $discountAmount + $taxAmount + $shippingAmount;

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingAmount,
            'total' => $total,
        ];
    }
}
