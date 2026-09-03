<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed                                             config(?string $key = null, mixed $default = null)
 * @method static class-string<\Illuminate\Database\Eloquent\Model> model(string $key)
 * @method static \Illuminate\Database\Eloquent\Model               newModel(string $key)
 *
 * @see \Karnoweb\Commerce\Commerce
 */
class Commerce extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'commerce';
    }
}
