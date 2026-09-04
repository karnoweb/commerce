<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Events;

/**
 * Lean package-level event dispatched (after commit, via
 * CommerceEventDispatcher) whenever PaymentService::initiate() creates a
 * PENDING payment. Useful for host integrations that want to react before
 * a gateway outcome is known (e.g. abandoned-payment tracking).
 */
final readonly class PaymentInitiated
{
    public function __construct(
        public int|string $orderId,
        public int|string $paymentId,
        public int|float $amount,
        public int|string|null $userId = null,
    ) {}
}
