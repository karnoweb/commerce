<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by ReturnService::process() when finalize*() is called without
 * a single addLine() call — a return must reference at least one
 * original sale line.
 */
class CannotReturnWithoutLines extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $orderId = null,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.cannot_return_without_lines'),
            $code,
            $previous
        );
    }
}
