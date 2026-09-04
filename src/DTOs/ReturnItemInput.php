<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

/**
 * Normalized input for ReturnService::process() — one returned quantity
 * against a specific original sale line (`OrderItem`).
 */
final readonly class ReturnItemInput
{
    public function __construct(
        public int|string $orderItemId,
        public int $quantity,
        public ?string $reason = null,
    ) {}
}
