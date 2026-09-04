<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by PaymentService::initiate() when the target order has already
 * been cancelled.
 */
class CannotPayCancelledOrder extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $orderId = null,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.cannot_pay_cancelled_order'),
            $code,
            $previous
        );
    }
}
