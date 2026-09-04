<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support\Calculators;

use Illuminate\Support\Collection;
use Karnoweb\Commerce\Contracts\DiscountCalculatorContract;

/**
 * Default no-op discount calculator: always 0. Real discount rules
 * (campaigns, user groups, ...) are host-specific business logic — bind
 * {@see DiscountCalculatorContract} to your own implementation to compute one.
 */
final class NullDiscountCalculator implements DiscountCalculatorContract
{
    public function calculate(Collection $items, array $context): int|float
    {
        return 0;
    }
}
