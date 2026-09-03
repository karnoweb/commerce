<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Feature;

use Karnoweb\Commerce\Commerce;
use Karnoweb\Commerce\CommerceServiceProvider;
use Karnoweb\Commerce\Facades\Commerce as CommerceFacade;
use Karnoweb\Commerce\Models\BaseModel;
use Karnoweb\Commerce\Tests\TestCase;

final class PackageBootstrapTest extends TestCase
{
    public function test_service_provider_is_registered(): void
    {
        $this->assertTrue($this->app->providerIsLoaded(CommerceServiceProvider::class));
    }

    public function test_commerce_singleton_and_facade_resolve(): void
    {
        $this->assertTrue($this->app->bound('commerce'));
        $this->assertSame($this->app->make('commerce'), $this->app->make('commerce'));
        $this->assertInstanceOf(Commerce::class, CommerceFacade::getFacadeRoot());
        $this->assertSame('', CommerceFacade::config('tables.prefix'));
    }

    public function test_base_model_respects_empty_prefix(): void
    {
        $model = new class extends BaseModel {
            protected $table = 'orders';
        };

        $this->assertSame('orders', $model->getTable());
    }

    public function test_translations_are_loaded(): void
    {
        $this->assertSame('Commerce', __('commerce::commerce.name'));
    }

    public function test_commerce_model_resolver(): void
    {
        config(['commerce.models.order' => \Karnoweb\Commerce\Models\Order::class]);

        $this->assertSame(\Karnoweb\Commerce\Models\Order::class, CommerceFacade::model('order'));
    }

    public function test_commerce_supports_macros(): void
    {
        CommerceFacade::macro('testMacro', fn (): string => 'ok');

        $this->assertSame('ok', CommerceFacade::testMacro());
    }
}
