<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by ReturnService::process() when a requested return quantity,
 * combined with quantities already returned against the same OrderItem,
 * would exceed the quantity originally sold on that line.
 */
class ReturnQuantityExceedsAvailable extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $orderItemId = null,
        public readonly int $requestedQuantity = 0,
        public readonly int $availableQuantity = 0,
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
