<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by ReturnService::process() when an `orderItemId` passed to
 * addItem() does not belong to the order the return targets.
 */
class ReturnItemNotFoundInOrder extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $orderItemId = null,
        public readonly int|string|null $orderId = null,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.return_item_not_found_in_order', [
                'item' => $orderItemId,
                'order' => $orderId,
            ]),
            $code,
            $previous
        );
    }
}
