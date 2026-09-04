<?php

declare(strict_types=1);

namespace Karnoweb\Commerce;

use Illuminate\Support\ServiceProvider;
use Karnoweb\Commerce\Contracts\CommerceContextResolverContract;
use Karnoweb\Commerce\Contracts\InvoiceNumberGeneratorContract;
use Karnoweb\Commerce\Contracts\OrderNumberGeneratorContract;
use Karnoweb\Commerce\Services\CartService;
use Karnoweb\Commerce\Services\CheckoutService;
use Karnoweb\Commerce\Services\InvoiceService;
use Karnoweb\Commerce\Services\OrderService;
use Karnoweb\Commerce\Services\PaymentService;
use Karnoweb\Commerce\Services\RefundService;
use Karnoweb\Commerce\Services\ReturnService;
use Karnoweb\Commerce\Services\WalletService;
use Karnoweb\Commerce\Support\CommerceMorphMap;
use Karnoweb\Commerce\Support\NullCommerceContextResolver;
use Karnoweb\Commerce\Support\SequentialInvoiceNumberGenerator;
use Karnoweb\Commerce\Support\SequentialOrderNumberGenerator;

class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/commerce.php', 'commerce');

        $this->app->singleton(CartService::class);
        $this->app->singleton(WalletService::class);
        $this->app->singleton(OrderService::class);

        $this->app->singleton(OrderNumberGeneratorContract::class, function ($app) {
            $class = config('commerce.numbers.order.generator', SequentialOrderNumberGenerator::class);

            return $app->make(is_string($class) && $class !== '' ? $class : SequentialOrderNumberGenerator::class);
        });

        $this->app->singleton(InvoiceNumberGeneratorContract::class, function ($app) {
            $class = config('commerce.numbers.invoice.generator', SequentialInvoiceNumberGenerator::class);

            return $app->make(is_string($class) && $class !== '' ? $class : SequentialInvoiceNumberGenerator::class);
        });

        $this->app->singleton(CommerceContextResolverContract::class, NullCommerceContextResolver::class);

        $this->app->singleton(InvoiceService::class);
        $this->app->singleton(PaymentService::class);

        $this->app->singleton(CheckoutService::class, function ($app) {
            return new CheckoutService(
                $app->make(CartService::class),
                $app->make(InvoiceService::class),
                $app->make(OrderNumberGeneratorContract::class),
                $app->make(PaymentService::class),
                $app->make(CommerceContextResolverContract::class),
            );
        });

        $this->app->singleton(RefundService::class, function ($app) {
            return new RefundService(
                $app->make(WalletService::class),
                $app->make(OrderService::class),
            );
        });

        $this->app->singleton(ReturnService::class, function ($app) {
            return new ReturnService(
                $app->make(WalletService::class),
                $app->make(OrderService::class),
            );
        });

        $this->app->singleton('commerce', function ($app) {
            return new Commerce(
                $app->make(CartService::class),
                $app->make(CheckoutService::class),
                $app->make(InvoiceService::class),
                $app->make(PaymentService::class),
                $app->make(RefundService::class),
                $app->make(WalletService::class),
                $app->make(ReturnService::class),
                $app->make(OrderService::class),
            );
        });
        $this->app->singleton(Commerce::class, fn ($app) => $app->make('commerce'));
    }

    public function boot(): void
    {
        CommerceMorphMap::register();

        $this->loadTranslationsFrom(__DIR__.'/../lang', 'commerce');

        // Single squashed schema — see the migration file's own docblock.
        // database/migrations_legacy/* is kept for historical reference
        // only and is never loaded.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations_squashed');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/commerce.php' => config_path('commerce.php'),
            ], 'commerce-config');

            $this->publishes([
                __DIR__.'/../database/migrations_squashed' => database_path('migrations'),
            ], 'commerce-migrations');

            $this->publishes([
                __DIR__.'/../lang' => lang_path('vendor/commerce'),
            ], 'commerce-lang');

            $this->publishes([
                __DIR__.'/../database/seeders' => database_path('seeders'),
            ], 'commerce-seeders');
        }
    }
}
