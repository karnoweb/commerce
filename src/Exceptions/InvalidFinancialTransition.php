<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when a service attempts a financial_status change that
 * FinancialStateMachine does not allow (e.g. paid → pending).
 */
class InvalidFinancialTransition extends RuntimeException
{
    public function __construct(
        public readonly string $from = '',
        public readonly string $to = '',
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.invalid_financial_transition', [
                'from' => $from,
                'to' => $to,
            ]),
            $code,
            $previous
        );
    }
}
