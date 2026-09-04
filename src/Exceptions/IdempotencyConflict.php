<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when an idempotency key is reused with a different payload
 * (e.g. a different user/order/amount) than the request that originally
 * created the record. Retrying with the *same* payload is safe and
 * returns the existing record instead of throwing.
 */
class IdempotencyConflict extends RuntimeException
{
    public function __construct(
        public readonly string $idempotencyKey = '',
        string $message = '',
        int $code = 409,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.idempotency_conflict', [
                'key' => $idempotencyKey,
            ]),
            $code,
            $previous
        );
    }
}
