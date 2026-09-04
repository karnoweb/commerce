<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Contracts;

/**
 * Produces an order_number when CheckoutBuilder::orderNumber() was not
 * called. The default sequential strategy is pluggable: bind a different
 * implementation to this contract in the host container.
 */
interface OrderNumberGeneratorContract
{
    public function generate(int|string|null $branchId = null, ?int $year = null): string;
}
