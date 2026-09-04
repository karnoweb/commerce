<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Events;

/**
 * Lean package-level event dispatched (after commit, via
 * CommerceEventDispatcher) whenever an Invoice is created — both from
 * CheckoutService::finalize() (order-bound, mandatory) and from
 * InvoiceService::issueStandalone() (order_id null).
 */
final readonly class InvoiceIssued
{
    public function __construct(
        public int|string $invoiceId,
        public int|string|null $orderId = null,
        public int|float $amount = 0,
        public int|string|null $userId = null,
    ) {}
}
