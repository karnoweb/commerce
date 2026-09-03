<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Events;

final readonly class OrderPaid
{
    public function __construct(
        public int|string $orderId,
        public int|string|null $userId = null,
    ) {}
}
