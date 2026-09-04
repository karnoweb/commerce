<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;

/**
 * Returned by CheckoutService::finalize(): an Order always comes with its
 * mandatory Invoice — Commerce never leaves an order unbilled.
 */
final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public Invoice $invoice,
    ) {}
}
