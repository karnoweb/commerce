<?php

declare(strict_types=1);

namespace Karnoweb\Commerce\Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class SourceScanner
{
    /**
     * @return list<string>
     */
    public static function phpFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    /**
     * @return list<string>
     */
    public static function importedAndQualifiedNames(string $contents): array
    {
        $names = [];

        if (preg_match_all('/^use\s+([^;]+);/m', $contents, $matches)) {
            foreach ($matches[1] as $import) {
                $import = trim(explode(' as ', $import)[0]);
                if (! str_starts_with($import, 'function ') && ! str_starts_with($import, 'const ')) {
                    $names[] = ltrim($import, '\\');
                }
            }
        }

        if (preg_match_all('/\b([A-Z][A-Za-z0-9_\\\\]+)/', $contents, $fqMatches)) {
            foreach ($fqMatches[1] as $name) {
                if (str_contains($name, '\\')) {
                    $names[] = ltrim($name, '\\');
                }
            }
        }

        return array_values(array_unique($names));
    }
}
