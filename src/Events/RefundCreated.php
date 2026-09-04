<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Events;

/**
 * Lean package-level event dispatched (after commit, via
 * CommerceEventDispatcher) whenever RefundService::process() creates an
 * OrderReturn.
 *
 * @param  int|string  $orderId
 * @param  int|string  $orderReturnId
 * @param  int|string|null  $userId
 */
final readonly class RefundCreated
{
    public function __construct(
        public int|string $orderId,
        public int|string $orderReturnId,
        public int|float $amount,
        public int|string|null $userId = null,
    ) {}
}
