<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Architecture;

use Karnoweb\Commerce\Tests\Support\SourceScanner;
use Karnoweb\Commerce\Tests\TestCase;

final class NoHostDependencyTest extends TestCase
{
    /** @var list<string> */
    private const FORBIDDEN = [
        'Karnoweb\\Accounting',
        'Karnoweb\\Crm',
        'Karnoweb\\Hr',
        'Karnoweb\\Payment',
        'Karnoweb\\Shop',
        'Karnoweb\\SmsSender',
        'Karnoweb\\LaravelTicketChat',
        'Illuminate\\Mail',
        'Illuminate\\Notifications',
        'App\\Models\\',
    ];

    /** @var list<string> */
    private const ALLOWED_ILLUMINATE_PREFIXES = [
        'Illuminate\\Foundation',
        'Illuminate\\Database',
        'Illuminate\\Support',
        'Illuminate\\Events',
        'Illuminate\\Console',
        'Illuminate\\Contracts',
    ];

    public function test_src_does_not_import_forbidden_namespaces(): void
    {
        $src = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src';

        foreach (SourceScanner::phpFiles($src) as $file) {
            $contents = (string) file_get_contents($file);
            $names = SourceScanner::importedAndQualifiedNames($contents);

            foreach ($names as $name) {
                foreach (self::FORBIDDEN as $forbidden) {
                    $this->assertFalse(
                        self::matchesPrefix($name, $forbidden),
                        "Forbidden namespace [{$forbidden}] referenced as [{$name}] in {$file}"
                    );
                }
            }

            $this->assertDoesNotMatchRegularExpression(
                '/app\\\\models\\\\/i',
                $contents,
                "Host App\\Models reference found in {$file}"
            );
        }
    }

    public function test_illuminate_usage_stays_within_allowed_prefixes(): void
    {
        $src = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'src';

        foreach (SourceScanner::phpFiles($src) as $file) {
            foreach (SourceScanner::importedAndQualifiedNames((string) file_get_contents($file)) as $name) {
                if (! str_starts_with(strtolower($name), 'illuminate\\')) {
                    continue;
                }

                $allowed = false;
                foreach (self::ALLOWED_ILLUMINATE_PREFIXES as $prefix) {
                    if (self::matchesPrefix($name, $prefix)) {
                        $allowed = true;

                        break;
                    }
                }

                $this->assertTrue(
                    $allowed,
                    "Illuminate type [{$name}] in {$file} is outside the allowed Commerce Illuminate surface."
                );
            }
        }
    }

    private static function matchesPrefix(string $name, string $prefix): bool
    {
        return str_starts_with(strtolower($name), strtolower($prefix));
    }
}
