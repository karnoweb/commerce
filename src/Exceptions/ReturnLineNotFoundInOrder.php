<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by ReturnService::process() when an `orderLineId` passed to
 * addLine() does not belong to the order the return targets.
 */
class ReturnLineNotFoundInOrder extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $orderLineId = null,
        public readonly int|string|null $orderId = null,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.return_line_not_found_in_order', [
                'line' => $orderLineId,
                'order' => $orderId,
            ]),
            $code,
            $previous
        );
    }
}
