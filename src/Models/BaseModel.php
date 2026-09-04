<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Karnoweb\Commerce\Support\CommerceTables;

abstract class BaseModel extends Model
{
    /** @var list<string> */
    protected $guarded = ['id'];

    /**
     * The physical table name is always resolved through
     * {@see CommerceTables::name()}: the logical key is either an explicit
     * `$this->table` (set by a subclass) or the conventional snake/plural
     * class basename (e.g. `OrderLine` -> `order_lines`), then prefixed via
     * `config('commerce.general.prefix')` (default `com_`) with any
     * per-table override from `config('commerce.tables')` applied first.
     */
    public function getTable(): string
    {
        $key = $this->table ?? str_replace('\\', '', Str::snake(Str::plural(class_basename($this))));

        return CommerceTables::name($key);
    }
}
