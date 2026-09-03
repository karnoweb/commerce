<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Events;

/**
 * @param int|string      $orderId
 * @param int|string|null $userId
 */
final readonly class OrderCreated
{
    public function __construct(
        public int|string $orderId,
        public int|string|null $userId = null,
    ) {}
}
