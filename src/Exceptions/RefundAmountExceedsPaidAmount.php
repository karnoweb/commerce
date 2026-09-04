<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by RefundService::process() when the requested refund amount,
 * combined with any amount already refunded for the order, would exceed
 * the order total.
 */
class RefundAmountExceedsPaidAmount extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $orderId = null,
        public readonly float $requestedAmount = 0.0,
        public readonly float $availableAmount = 0.0,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.refund_amount_exceeds_paid_amount', [
                'requested' => $requestedAmount,
                'available' => $availableAmount,
            ]),
            $code,
            $previous
        );
    }
}
