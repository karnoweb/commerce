<?php

declare(strict_types=1);

namespace Karnoweb\Commerce;

use Illuminate\Support\Traits\Macroable;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

class Commerce
{
    use Macroable;
    use ResolvesConfiguredModels;

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return config('commerce');
        }

        return config('commerce.' . $key, $default);
    }
}
