<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use Karnoweb\Commerce\Models\BaseModel;

/**
 * Resolves a logical table key (e.g. `'orders'`, `'order_lines'`) to its
 * final, prefixed physical table name, honouring per-table overrides in
 * `config('commerce.tables')` and the global `config('commerce.general.prefix')`
 * (default `com_`) — same pattern as `karnoweb/laravel-inventory`.
 *
 * Every migration and every {@see BaseModel}
 * subclass goes through this single choke point, so a host can rename or
 * re-prefix the entire schema by publishing `config/commerce.php` — no
 * code changes required.
 */
final class CommerceTables
{
    public static function prefix(): string
    {
        return (string) config('commerce.general.prefix', 'com_');
    }

    /**
     * @param  string  $key  Logical table key, e.g. 'orders', 'order_lines'.
     */
    public static function name(string $key): string
    {
        $prefix = self::prefix();
        $table = (string) config("commerce.tables.{$key}", $key);

        if ($prefix === '' || str_starts_with($table, $prefix)) {
            return $table;
        }

        return $prefix.$table;
    }
}
