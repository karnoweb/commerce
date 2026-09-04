<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

use Karnoweb\Commerce\Models\Invoice;
use Karnoweb\Commerce\Models\Order;
use Karnoweb\Commerce\Models\Payment;

/**
 * Returned by CheckoutService::finalizeWithPayments(): the mandatory
 * invoice plus 1..n PENDING payment records (no gateway call).
 */
final readonly class CheckoutResultWithPayments
{
    /**
     * @param  list<Payment>  $payments
     */
    public function __construct(
        public Order $order,
        public Invoice $invoice,
        public array $payments,
    ) {}
}
