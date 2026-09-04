<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Architecture;

use Karnoweb\Commerce\Tests\Support\SourceScanner;
use Karnoweb\Commerce\Tests\TestCase;

/**
 * Dedicated, narrower guard for the new Builders/Services/DTOs layers added
 * for the Facade-centric cart/checkout/payment/refund/wallet API. Redundant
 * with {@see NoHostDependencyTest}'s package-wide scan, but kept explicit
 * per the mission's mandatory test list — these directories are the ones
 * most likely to accidentally reach for a shop model or host session state.
 */
final class NewLayersHaveNoHostOrShopDependencyTest extends TestCase
{
    /** @var list<string> */
    private const DIRECTORIES = ['Builders', 'Services', 'DTOs'];

    public function test_new_layers_do_not_import_app_namespace(): void
    {
        $src = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'src';

        foreach (self::DIRECTORIES as $directory) {
            foreach (SourceScanner::phpFiles($src.DIRECTORY_SEPARATOR.$directory) as $file) {
                $contents = (string) file_get_contents($file);

                $this->assertDoesNotMatchRegularExpression(
                    '/^use\s+App\\\\/mi',
                    $contents,
                    "src/{$directory} file {$file} imports a host App\\ namespace."
                );

                $this->assertDoesNotMatchRegularExpression(
                    '/\bApp\\\\Models\\\\/i',
                    $contents,
                    "src/{$directory} file {$file} references App\\Models\\*."
                );
            }
        }
    }

    public function test_new_layers_do_not_import_shop_package(): void
    {
        $src = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'src';

        foreach (self::DIRECTORIES as $directory) {
            foreach (SourceScanner::phpFiles($src.DIRECTORY_SEPARATOR.$directory) as $file) {
                $contents = (string) file_get_contents($file);

                $this->assertDoesNotMatchRegularExpression(
                    '/Karnoweb\\\\Shop/i',
                    $contents,
                    "src/{$directory} file {$file} references Karnoweb\\Shop, a hard dependency on another domain package."
                );
            }
        }
    }

    public function test_new_layers_do_not_use_auth_or_request_helpers(): void
    {
        $src = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Services';

        foreach (SourceScanner::phpFiles($src) as $file) {
            $contents = (string) file_get_contents($file);

            $this->assertDoesNotMatchRegularExpression(
                '/\b(auth|request)\s*\(/',
                $contents,
                "Service {$file} calls auth()/request() — services must stay package-safe (no host session state)."
            );
        }
    }
}
