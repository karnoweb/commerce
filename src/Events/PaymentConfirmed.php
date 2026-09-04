<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Events;

/**
 * Lean package-level event dispatched (after commit, via
 * CommerceEventDispatcher) whenever PaymentService::confirm() marks a
 * payment PAID. Complements {@see OrderPaid} (dispatched too, only when
 * the payment is order-bound) — prefer this one for invoice/standalone
 * payment integrations since it is always dispatched.
 */
final readonly class PaymentConfirmed
{
    public function __construct(
        public int|string $paymentId,
        public int|string $invoiceId,
        public int|string|null $orderId,
        public int|float $amount,
        public int|string|null $userId = null,
    ) {}
}
