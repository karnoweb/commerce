<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by ReturnService::process() when a requested return quantity,
 * combined with quantities already returned against the same OrderLine,
 * would exceed the quantity originally sold on that line.
 */
class ReturnQuantityExceedsAvailable extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $orderLineId = null,
        public readonly int|float $requestedQuantity = 0,
        public readonly int|float $availableQuantity = 0,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.return_quantity_exceeds_available', [
                'requested' => $requestedQuantity,
                'available' => $availableQuantity,
            ]),
            $code,
            $previous
        );
    }
}
