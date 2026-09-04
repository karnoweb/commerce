<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown by CheckoutService::finalize() when the user has no cart lines
 * (OrderLine rows with a null order_id) to check out.
 */
class CannotCheckoutEmptyCart extends RuntimeException
{
    public function __construct(
        public readonly int|string|null $userId = null,
        string $message = '',
        int $code = 422,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            $message ?: __('commerce::commerce.messages.cannot_checkout_empty_cart'),
            $code,
            $previous
        );
    }
}
