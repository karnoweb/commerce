<?php

declare(strict_types=1);

namespace Karnoweb\Commerce;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Commerce\Services\CartService;
use Karnoweb\Commerce\Services\CheckoutService;
use Karnoweb\Commerce\Services\PaymentService;
use Karnoweb\Commerce\Services\RefundService;
use Karnoweb\Commerce\Services\WalletService;
use Karnoweb\Commerce\Support\CommerceMorphMap;

class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/commerce.php', 'commerce');

        $this->app->singleton(CartService::class);
        $this->app->singleton(WalletService::class);
        $this->app->singleton(PaymentService::class);

        $this->app->singleton(CheckoutService::class, function ($app) {
            return new CheckoutService($app->make(CartService::class));
        });

        $this->app->singleton(RefundService::class, function ($app) {
            return new RefundService($app->make(WalletService::class));
        });

        $this->app->singleton('commerce', function ($app) {
            return new Commerce(
                $app->make(CartService::class),
                $app->make(CheckoutService::class),
                $app->make(PaymentService::class),
                $app->make(RefundService::class),
                $app->make(WalletService::class),
            );
        });
        $this->app->singleton(Commerce::class, fn ($app) => $app->make('commerce'));
    }

    public function boot(): void
    {
        CommerceMorphMap::register();

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'commerce');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/commerce.php' => config_path('commerce.php'),
            ], 'commerce-config');

            // Keep fixed 2022_* filenames so published migrations run early and keep stable names.
            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'commerce-migrations');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/commerce'),
            ], 'commerce-lang');
        }
    }
}
