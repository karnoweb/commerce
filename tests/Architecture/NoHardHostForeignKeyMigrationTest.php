<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Architecture;

use Karnoweb\Commerce\Tests\Support\SourceScanner;
use Karnoweb\Commerce\Tests\TestCase;

/**
 * Guards the DB layer against the same boundary rules {@see NoHostDependencyTest}
 * enforces for PHP source: migrations must not hard-couple this package's schema to
 * host tables (users, addresses, branches, user_groups) or to another domain
 * package's tables (products, owned by karnoweb/shop). Cross-boundary references
 * must stay soft (unsignedBigInteger + index, never ->constrained()/->foreign()).
 */
final class NoHardHostForeignKeyMigrationTest extends TestCase
{
    /** @var list<string> Host or cross-domain tables this package must never hard-FK. */
    private const FORBIDDEN_TABLES = [
        'users',
        'user_groups',
        'addresses',
        'branches',
        'products',
    ];

    /** @var list<string> Columns that would implicitly FK a forbidden table via bare ->constrained(). */
    private const IMPLICIT_HOST_COLUMNS = [
        'user_id',
        'cancel_by',
        'delivered_by',
        'created_by',
        'causer_id',
        'product_id',
        'user_group_id',
    ];

    public function test_migrations_do_not_hard_foreign_key_host_or_cross_domain_tables(): void
    {
        $migrations = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'migrations';

        foreach (SourceScanner::phpFiles($migrations) as $file) {
            $contents = (string) file_get_contents($file);

            foreach (self::FORBIDDEN_TABLES as $table) {
                $quoted = preg_quote($table, '/');

                $this->assertDoesNotMatchRegularExpression(
                    "/->constrained\\(\\s*['\"]{$quoted}['\"]/i",
                    $contents,
                    "Migration {$file} declares ->constrained('{$table}'), a hard FK to a host/cross-domain table."
                );

                $this->assertDoesNotMatchRegularExpression(
                    "/->on\\(\\s*['\"]{$quoted}['\"]/i",
                    $contents,
                    "Migration {$file} declares ->on('{$table}'), a hard FK to a host/cross-domain table."
                );
            }

            $columns = implode('|', self::IMPLICIT_HOST_COLUMNS);
            if (preg_match("/foreignId\\(\\s*['\"]({$columns})['\"]\\s*\\)[^;]*?->constrained\\(\\s*\\)/s", $contents, $matches)) {
                $this->fail("Migration {$file} declares a bare ->constrained() on host/cross-domain column [{$matches[1]}]; use a soft unsignedBigInteger + index instead.");
            }

            $this->assertDoesNotMatchRegularExpression(
                '/^use\s+App\\\\/m',
                $contents,
                "Migration {$file} imports a host App\\ namespace (e.g. App\\Enums\\*). Migrations must be installable standalone."
            );
        }
    }
}
