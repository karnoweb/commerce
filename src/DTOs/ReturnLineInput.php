<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\DTOs;

/**
 * Normalized input for ReturnService::process() — one returned quantity
 * against a specific original sale line (`OrderLine`). `returnReasonId` is
 * a soft/internal reference to a `ReturnReason` row (see
 * database/seeders/CommerceSeeder.php for the seeded defaults);
 * `reasonNote` is an optional free-text note alongside it.
 */
final readonly class ReturnLineInput
{
    public function __construct(
        public int|string $orderLineId,
        public int|float $quantity,
        public int|string|null $returnReasonId = null,
        public ?string $reasonNote = null,
    ) {}
}
