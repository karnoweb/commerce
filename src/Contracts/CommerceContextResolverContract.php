<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Contracts;

/**
 * Optional host hook for checkout defaults. When CheckoutBuilder::
 * branchId() is omitted (or passed null), CheckoutService asks this
 * resolver. The package default always returns null.
 */
interface CommerceContextResolverContract
{
    public function branchId(): int|string|null;
}
