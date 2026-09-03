<?php

declare(strict_types=1);

namespace Karnoweb\Commerce;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Commerce\Support\CommerceMorphMap;

class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/commerce.php', 'commerce');

        $this->app->singleton('commerce', fn () => new Commerce);
        $this->app->singleton(Commerce::class, fn ($app) => $app->make('commerce'));
    }

    public function boot(): void
    {
        CommerceMorphMap::register();

        $this->loadTranslationsFrom(__DIR__ . '/../lang', 'commerce');
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/commerce.php' => config_path('commerce.php'),
            ], 'commerce-config');

            // Keep fixed 2022_* filenames so published migrations run early and keep stable names.
            $this->publishes([
                __DIR__ . '/../database/migrations' => database_path('migrations'),
            ], 'commerce-migrations');

            $this->publishes([
                __DIR__ . '/../lang' => lang_path('vendor/commerce'),
            ], 'commerce-lang');
        }
    }
}
