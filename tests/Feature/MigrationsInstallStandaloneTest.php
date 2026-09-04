<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Karnoweb\Commerce\Support\CommerceTables;
use Karnoweb\Commerce\Tests\Support\SourceScanner;
use Karnoweb\Commerce\Tests\TestCase;

/**
 * Proves the package is installable/testable/removable standalone (no host app,
 * no App\Models, no App\Enums, no host `users`/`branches`/`addresses`/`products`
 * tables): migrate must succeed on a bare sqlite connection from a *single*
 * squashed migration, and rollback must cleanly reverse it — under both the
 * real default table prefix (`com_`) and a custom one.
 */
final class MigrationsInstallStandaloneTest extends TestCase
{
    /** @var list<string> Logical table keys — see config('commerce.tables'). */
    private const EXPECTED_TABLE_KEYS = [
        'orders',
        'order_lines',
        'document_adjustments',
        'document_dimensions',
        'invoices',
        'payment_methods',
        'shipping_methods',
        'payments',
        'transactions',
        'return_reasons',
        'order_returns',
        'order_return_lines',
        'wallets',
        'wallet_transactions',
        'discounts',
        'discount_user_group',
    ];

    public function test_only_one_squashed_migration_file_is_shipped(): void
    {
        $migrations = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations_squashed';

        $this->assertCount(
            1,
            SourceScanner::phpFiles($migrations),
            'database/migrations_squashed must contain exactly one migration file.'
        );
    }

    public function test_migrate_creates_expected_tables_under_the_default_prefix(): void
    {
        $this->assertSame('com_', config('commerce.general.prefix'), 'The default table prefix must be com_.');

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        foreach (self::EXPECTED_TABLE_KEYS as $key) {
            $table = CommerceTables::name($key);
            $this->assertStringStartsWith('com_', $table);
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist after migrate.");
        }

        // Never provided by this package — proves no hard dependency was created on them.
        foreach (['users', 'products', 'user_groups', 'addresses', 'branches'] as $hostTable) {
            $this->assertFalse(Schema::hasTable($hostTable), "Host/cross-domain table [{$hostTable}] must not be created by this package.");
        }
    }

    public function test_migrate_respects_a_custom_table_prefix(): void
    {
        config(['commerce.general.prefix' => 'xyz_']);

        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $this->assertTrue(Schema::hasTable('xyz_orders'));
        $this->assertTrue(Schema::hasTable('xyz_order_lines'));
        $this->assertFalse(Schema::hasTable('com_orders'), 'Only the configured prefix should be used, not the package default.');

        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);

        $this->assertFalse(Schema::hasTable('xyz_orders'));
    }

    public function test_order_lines_has_no_product_id_column(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        $table = CommerceTables::name('order_lines');

        $this->assertFalse(
            Schema::hasColumn($table, 'product_id'),
            'order_lines must not have a product_id column — lines are generic item_type/item_id/item_name references.'
        );
        $this->assertTrue(Schema::hasColumn($table, 'item_type'));
        $this->assertTrue(Schema::hasColumn($table, 'item_id'));
        $this->assertTrue(Schema::hasColumn($table, 'item_name'));
    }

    public function test_orders_and_invoices_have_no_fixed_tax_discount_shipping_columns(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);

        foreach (['discount_amount', 'tax_amount', 'shipping_amount'] as $column) {
            $this->assertFalse(
                Schema::hasColumn(CommerceTables::name('orders'), $column),
                "orders must not have a fixed [{$column}] column — document_adjustments is the single source of truth."
            );
        }

        foreach (['tax_amount', 'discount_amount'] as $column) {
            $this->assertFalse(
                Schema::hasColumn(CommerceTables::name('invoices'), $column),
                "invoices must not have a fixed [{$column}] column — document_adjustments is the single source of truth."
            );
        }
    }

    public function test_migrate_rollback_cleanly_reverses_every_migration(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $this->artisan('migrate:rollback', ['--force' => true])->assertExitCode(0);

        foreach (self::EXPECTED_TABLE_KEYS as $key) {
            $table = CommerceTables::name($key);
            $this->assertFalse(Schema::hasTable($table), "Expected table [{$table}] to be dropped after rollback.");
        }
    }
}
