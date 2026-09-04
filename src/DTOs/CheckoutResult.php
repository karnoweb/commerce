<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\Payment;

/**
 * Optional bundle of the records produced while walking a checkout through
 * order -> invoice -> payment. Each leg is independently optional so a host
 * can stop after placing the order.
 */
final readonly class CheckoutResult
{
    public function __construct(
        public Order $order,
        public ?Invoice $invoice = null,
        public ?Payment $payment = null,
    ) {}
}
