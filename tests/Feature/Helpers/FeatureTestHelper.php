<?php

namespace Tests\Feature\Helpers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FeatureTestHelper
{
    private static ?string $testDir = null;

    /**
     * Setup test directory for the current test
     */
    public static function setupTestDirectory(): void
    {
        self::$testDir = sys_get_temp_dir() . '/hibla_feature_test_' . uniqid();
        mkdir(self::$testDir, 0777, true);
    }

    /**
     * Get the test directory path
     */
    public static function getTestDirectory(): string
    {
        if (self::$testDir === null) {
            throw new \RuntimeException('Test directory not initialized. Call setupTestDirectory() first.');
        }

        return self::$testDir;
    }

    /**
     * Get a test file path
     */
    public static function getTestPath(string $filename): string
    {
        return self::getTestDirectory() . '/' . $filename;
    }

    /**
     * Cleanup test directory and all its contents
     */
    public static function cleanupTestDirectory(): void
    {
        if (self::$testDir === null || ! is_dir(self::$testDir)) {
            return;
        }

        self::removeDirectoryRecursive(self::$testDir);
        self::$testDir = null;
    }

    /**
     * Recursively remove a directory and its contents
     */
    private static function removeDirectoryRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            @$todo($fileinfo->getRealPath());
        }

        @rmdir($dir);
    }

    /**
     * Create a file with content
     */
    public static function createFile(string $filename, string $content): string
    {
        $path = self::getTestPath($filename);
        $dir = dirname($path);

        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Wait for file system operations to complete
     */
    public static function waitForFileSystem(int $milliseconds = 100): void
    {
        usleep($milliseconds * 1000);
    }
}