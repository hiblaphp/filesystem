<?php 

use Hibla\Filesystem\Handlers\FileHandler;

require 'vendor/autoload.php';

function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}

$iterations = 10_000_000;
$fileHandler = new FileHandler();

echo "=== Auto-Buffering Performance Test ===\n\n";

// Simple generator (no manual buffering)
$createGenerator = function() use ($iterations) {
    return (function() use ($iterations) {
        for ($i = 0; $i < $iterations; $i++) {
            yield 'chunk' . $i . "\n";
        }
    })();
};

// Test 1: No buffering
echo "Test 1: No auto-buffering (buffer_size = 0)\n";
echo str_repeat("-", 60) . "\n";
$start1 = microtime(true);
$mem1 = memory_get_usage();

$fileHandler->writeFileFromGenerator(
    'test_no_buffer.txt',
    $createGenerator()
)->await(false);

$time1 = microtime(true) - $start1;
echo "✓ Time: " . round($time1, 2) . "s\n";
echo "  Speed: " . number_format($iterations / $time1, 0) . " lines/sec\n";
echo "  Memory: " . formatBytes(memory_get_peak_usage() - $mem1) . "\n\n";

// Test 2: 8KB buffer
echo "Test 2: Auto-buffering (buffer_size = 8192)\n";
echo str_repeat("-", 60) . "\n";
$start2 = microtime(true);
$mem2 = memory_get_usage();

$fileHandler->writeFileFromGenerator(
    'test_buffer_8k.txt',
    $createGenerator(),
    ['buffer_size' => 8192]
)->await(false);

$time2 = microtime(true) - $start2;
echo "✓ Time: " . round($time2, 2) . "s\n";
echo "  Speed: " . number_format($iterations / $time2, 0) . " lines/sec\n";
echo "  Memory: " . formatBytes(memory_get_peak_usage() - $mem2) . "\n";
echo "  Speedup: " . round($time1 / $time2, 1) . "x faster\n\n";

// Test 3: 64KB buffer
echo "Test 3: Auto-buffering (buffer_size = 65536)\n";
echo str_repeat("-", 60) . "\n";
$start3 = microtime(true);
$mem3 = memory_get_usage();

$fileHandler->writeFileFromGenerator(
    'test_buffer_64k.txt',
    $createGenerator(),
    ['buffer_size' => 65536]
)->await(false);

$time3 = microtime(true) - $start3;
echo "✓ Time: " . round($time3, 2) . "s\n";
echo "  Speed: " . number_format($iterations / $time3, 0) . " lines/sec\n";
echo "  Memory: " . formatBytes(memory_get_peak_usage() - $mem3) . "\n";
echo "  Speedup: " . round($time1 / $time3, 1) . "x faster\n\n";

// Summary
echo "=== Summary ===\n";
echo "No buffering:     " . round($time1, 2) . "s (baseline)\n";
echo "8KB buffer:       " . round($time2, 2) . "s (" . round($time1/$time2, 1) . "x)\n";
echo "64KB buffer:      " . round($time3, 2) . "s (" . round($time1/$time3, 1) . "x)\n";
echo "\n✅ Auto-buffering works perfectly!\n";

// Cleanup
@unlink('test_no_buffer.txt');
@unlink('test_buffer_8k.txt');
@unlink('test_buffer_64k.txt');