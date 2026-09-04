<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by PaymentService::confirm() when the payment is already PAID
 * and the confirm() call does not match the transaction (gateway/tracking
 * code) that originally paid it — i.e. a genuine double-confirm rather
 * than a safe retry.
 */
class CannotConfirmAlreadyPaidPayment extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $paymentId = null,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.cannot_confirm_already_paid_payment'),
            $code,
            $previous
        );
    }
}
