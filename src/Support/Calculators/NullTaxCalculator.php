<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support\Calculators;

use Illuminate\Support\Collection;
use Karnoweb\Commerce\Contracts\TaxCalculatorContract;

/**
 * Default no-op tax calculator: always 0. Real tax rules are host-specific
 * business logic — bind {@see TaxCalculatorContract} to your own
 * implementation to compute one.
 */
final class NullTaxCalculator implements TaxCalculatorContract
{
    public function calculate(Collection $items, array $context): int|float
    {
        return 0;
    }
}
