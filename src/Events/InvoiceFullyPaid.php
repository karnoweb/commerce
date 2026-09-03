<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Events;

final readonly class InvoiceFullyPaid
{
    public function __construct(
        public int|string $invoiceId,
        public int|string|null $orderId = null,
    ) {}
}
