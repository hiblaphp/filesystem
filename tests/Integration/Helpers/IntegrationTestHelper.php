<?php

namespace Tests\Integration\Helpers;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class IntegrationTestHelper
{
    private static ?string $testDir = null;

    /**
     * Setup test directory for the current test
     */
    public static function setupTestDirectory(): void
    {
        self::$testDir = sys_get_temp_dir() . '/hibla_integration_test_' . uniqid();
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
     * Create multiple test files
     */
    public static function createTestFiles(array $files): array
    {
        $paths = [];
        foreach ($files as $filename => $content) {
            $path = self::getTestPath($filename);
            $dir = dirname($path);
            
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            
            file_put_contents($path, $content);
            $paths[$filename] = $path;
        }
        
        return $paths;
    }

    /**
     * Create a large file for testing
     */
    public static function createLargeFile(string $filename, int $lines = 10000, string $content = 'Line of text'): string
    {
        $path = self::getTestPath($filename);
        $data = implode("\n", array_fill(0, $lines, $content));
        file_put_contents($path, $data);
        
        return $path;
    }

    /**
     * Create a CSV file for testing
     */
    public static function createCsvFile(string $filename, array $headers, array $rows): string
    {
        $path = self::getTestPath($filename);
        $lines = [implode(',', $headers)];
        
        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }
        
        file_put_contents($path, implode("\n", $lines));
        
        return $path;
    }

    /**
     * Measure execution time of a callable
     */
    public static function measureTime(callable $callback): array
    {
        $start = microtime(true);
        $result = $callback();
        $duration = microtime(true) - $start;
        
        return [
            'result' => $result,
            'duration' => $duration,
        ];
    }
}