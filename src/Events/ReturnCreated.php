<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Events;

/**
 * Lean package-level event dispatched (after commit, via
 * CommerceEventDispatcher) whenever ReturnService::process() creates an
 * OrderReturn from quantity-based return lines. Distinct from
 * {@see RefundCreated} (amount-only, dispatched by the legacy RefundService)
 * — prefer this one for accounting/inventory integrations that need to know
 * exactly which lines and quantities came back.
 */
final readonly class ReturnCreated
{
    /**
     * @param  list<array{orderLineId: int|string, quantity: int|float, amount: int|float}>  $lines
     */
    public function __construct(
        public int|string $orderId,
        public int|string $orderReturnId,
        public int|float $totalAmount,
        public array $lines = [],
        public int|string|null $userId = null,
    ) {}
}
