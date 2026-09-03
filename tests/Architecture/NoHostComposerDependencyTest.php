<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Architecture;

use Karnoweb\Commerce\Tests\TestCase;

final class NoHostComposerDependencyTest extends TestCase
{
    /** @var list<string> */
    private const FORBIDDEN_PACKAGES = [
        'karnoweb/crm',
        'karnoweb/shop',
        'karnoweb/laravel-accounting',
        'karnoweb/hr',
        'karnoweb/payment',
        'karnoweb/sms-sender',
        'karnoweb/laravel-ticket-chat',
        'karnoweb/laravel-inventory',
    ];

    public function test_composer_json_has_no_forbidden_direct_dependencies(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json';
        $this->assertFileExists($path);

        /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $composer */
        $composer = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $direct = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);

        foreach (self::FORBIDDEN_PACKAGES as $package) {
            $this->assertArrayNotHasKey(
                $package,
                $direct,
                "composer.json must not directly require [{$package}]"
            );
        }

        foreach (array_keys($direct) as $name) {
            $this->assertFalse(
                str_starts_with((string) $name, 'karnoweb/')
                && ! in_array($name, ['karnoweb/commerce', 'karnoweb/translation'], true),
                "composer.json must not directly require Karnoweb package [{$name}]"
            );
        }
    }

    public function test_composer_json_requires_only_illuminate_and_documented_support_packages(): void
    {
        $path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'composer.json';

        /** @var array{require: array<string, string>} $composer */
        $composer = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $allowed = [
            'php',
            'illuminate/console',
            'illuminate/database',
            'illuminate/events',
            'illuminate/support',
            'karnoweb/translation',
        ];

        foreach (array_keys($composer['require']) as $name) {
            $this->assertContains(
                $name,
                $allowed,
                "Unexpected direct runtime dependency [{$name}]"
            );
        }
    }
}
