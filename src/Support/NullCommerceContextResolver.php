<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use Karnoweb\Commerce\Contracts\CommerceContextResolverContract;

/**
 * Package default: no host context. Checkout leaves branch_id null
 * unless the builder set it explicitly.
 */
final class NullCommerceContextResolver implements CommerceContextResolverContract
{
    public function branchId(): int|string|null
    {
        return null;
    }
}
