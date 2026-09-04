<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Database\Seeders;

use Illuminate\Database\Seeder;
use Karnoweb\Commerce\Support\ResolvesConfiguredModels;

/**
 * Seeds the small, package-owned catalogs a host typically needs before
 * using Commerce: payment methods, shipping methods, and normalized return
 * reasons. Safe to run repeatedly — every row is upserted by its natural
 * key (`provider`/`driver`/`code`).
 *
 * Run with:
 *   php artisan db:seed --class="Karnoweb\\Commerce\\Database\\Seeders\\CommerceSeeder"
 */
class CommerceSeeder extends Seeder
{
    use ResolvesConfiguredModels;

    public function run(): void
    {
        $this->seedPaymentMethods();
        $this->seedShippingMethods();
        $this->seedReturnReasons();
    }

    private function seedPaymentMethods(): void
    {
        $class = static::model('payment_method');

        foreach (['cash', 'card', 'online', 'wallet'] as $provider) {
            $class::query()->firstOrCreate(
                ['provider' => $provider],
                ['published' => true],
            );
        }
    }

    private function seedShippingMethods(): void
    {
        $class = static::model('shipping_method');

        foreach (['standard', 'pickup'] as $driver) {
            $class::query()->firstOrCreate(
                ['driver' => $driver],
                ['price' => 0, 'published' => true],
            );
        }
    }

    private function seedReturnReasons(): void
    {
        $class = static::model('return_reason');

        $defaults = [
            'damaged' => 'Damaged',
            'wrong_item' => 'Wrong item',
            'not_needed' => 'No longer needed',
            'other' => 'Other',
        ];

        $ordering = 0;

        foreach ($defaults as $code => $title) {
            $class::query()->firstOrCreate(
                ['code' => $code],
                ['title' => $title, 'published' => true, 'ordering' => $ordering++],
            );
        }
    }
}
