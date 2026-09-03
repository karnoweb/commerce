<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Karnoweb\Commerce\Tests\TestCase;

/**
 * Proves the package is installable/testable/removable standalone (no host app,
 * no App\Models, no App\Enums, no host `users`/`branches`/`addresses`/`products`
 * tables): migrate must succeed on a bare sqlite connection, and rollback must
 * cleanly reverse it.
 */
final class MigrationsInstallStandaloneTest extends TestCase
{
    public function test_migrate_creates_expected_tables_without_any_host_tables(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        foreach ([
            'orders',
            'order_items',
            'order_totals',
            'order_returns',
            'invoices',
            'payments',
            'transactions',
            'wallets',
            'wallet_transactions',
            'discounts',
            'discount_user_group',
            'payment_methods',
            'shipping_methods',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist after migrate.");
        }

        // Never provided by this package — proves no hard dependency was created on them.
        foreach (['users', 'products', 'user_groups', 'addresses', 'branches'] as $hostTable) {
            $this->assertFalse(Schema::hasTable($hostTable), "Host/cross-domain table [{$hostTable}] must not be created by this package.");
        }
    }

    public function test_migrate_rollback_cleanly_reverses_every_migration(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);

        foreach ([
            'orders',
            'order_items',
            'order_totals',
            'order_returns',
            'invoices',
            'payments',
            'transactions',
            'wallets',
            'wallet_transactions',
            'discounts',
            'discount_user_group',
            'payment_methods',
            'shipping_methods',
        ] as $table) {
            $this->assertFalse(Schema::hasTable($table), "Expected table [{$table}] to be dropped after rollback.");
        }
    }
}
