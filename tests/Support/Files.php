<?php

namespace JDZ\FontManager\Tests\Support;

/**
 * Temp folders and file URLs for the tests.
 */
final class Files
{
    /** A new empty folder under the system temp dir, forward slashes (as FontsDb normalises its path) */
    public static function tempDir(): string
    {
        $dir = str_replace('\\', '/', sys_get_temp_dir()) . '/jdz-fontmanager-' . bin2hex(random_bytes(6));
        mkdir($dir);

        return $dir;
    }

    public static function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (array_diff(scandir($dir), ['.', '..']) as $entry) {
            $path = $dir . '/' . $entry;
            is_dir($path) ? self::removeTree($path) : unlink($path);
        }

        rmdir($dir);
    }

    /** The file:// URL of a local path: curl reads it like a provider response, no network involved */
    public static function url(string $path): string
    {
        return 'file:///' . ltrim(str_replace('\\', '/', $path), '/');
    }
}
