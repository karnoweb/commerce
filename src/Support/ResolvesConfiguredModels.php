<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Support;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

trait ResolvesConfiguredModels
{
    protected static function configuredModelsRoot(): string
    {
        return 'commerce';
    }

    /**
     * @return class-string<Model>
     */
    public static function model(string $key): string
    {
        $class = config(static::configuredModelsRoot() . '.models.' . $key);

        if (! is_string($class) || $class === '' || ! class_exists($class)) {
            throw new InvalidArgumentException(
                sprintf('Commerce model [%s] is not configured or does not exist.', $key)
            );
        }

        return $class;
    }

    public static function newModel(string $key): Model
    {
        $class = static::model($key);

        return new $class;
    }
}
