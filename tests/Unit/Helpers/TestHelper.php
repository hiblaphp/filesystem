<?php

namespace Tests\Unit\Helpers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TestHelper
{
    private static ?string $testDir = null;

    public static function setupTestDirectory(): string
    {
        self::$testDir = sys_get_temp_dir() . '/hibla_unit_test_' . uniqid();
        mkdir(self::$testDir, 0777, true);

        return self::$testDir;
    }

    public static function getTestPath(string $filename): string
    {
        if (self::$testDir === null) {
            throw new \RuntimeException('Test directory not initialized. Call setupTestDirectory() first.');
        }

        return self::$testDir . '/' . $filename;
    }

    public static function cleanupTestDirectory(): void
    {
        if (self::$testDir === null || ! is_dir(self::$testDir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(self::$testDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
            $todo($fileinfo->getRealPath());
        }

        rmdir(self::$testDir);
        self::$testDir = null;
    }
}
