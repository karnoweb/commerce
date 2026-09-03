<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use Illuminate\Support\Facades\DB;

/**
 * Dispatch commerce domain events after the surrounding DB transaction commits.
 */
final class CommerceEventDispatcher
{
    public static function dispatch(object $event): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(static fn () => event($event));

            return;
        }

        event($event);
    }
}
