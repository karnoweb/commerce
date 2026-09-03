<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use Illuminate\Database\Eloquent\Relations\Relation;

final class CommerceMorphMap
{
    public static function register(): void
    {
        $map = config('commerce.morph_map', []);

        if ($map === []) {
            return;
        }

        Relation::morphMap($map, merge: true);
    }
}
