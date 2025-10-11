<?php

use Hibla\Filesystem\Handlers\FileHandler;

require 'vendor/autoload.php';

function fileHandler()
{
    return new FileHandler();
}

function test1Async()
{
    return async(function () {
        $start = microtime(true);
        await(fileHandler()->writeFileFromGenerator('large-test1.txt', (function () {
            $buffer = '';
            for ($i = 0; $i < 10_000_000; $i++) {
                $buffer .= 'chunk' . $i . "\n";
                if (strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            if ($buffer) yield $buffer;
        })()));
        $end = microtime(true);
        $time = $end - $start;
        echo "✓Async Time: " . round($time, 2) . "s\n";
    });
}

function test2Async()
{
    return async(function () {
        $start = microtime(true);
        await(fileHandler()->writeFileFromGenerator('large-test2.txt', (function () {
            $buffer = '';
            for ($i = 0; $i < 10_000_000; $i++) {
                $buffer .= 'chunk' . $i . "\n";
                if (strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            if ($buffer) yield $buffer;
        })()));
        $end = microtime(true);
        $time = $end - $start;
        echo "✓Async Time: " . round($time, 2) . "s\n";
    });
}

function test3Async()
{
    return async(function () {
        $start = microtime(true);
        await(fileHandler()->writeFileFromGenerator('large-test3.txt', (function () {
            $buffer = '';
            for ($i = 0; $i < 10_000_000; $i++) {
                $buffer .= 'chunk' . $i . "\n";
                if (strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            if ($buffer) yield $buffer;
        })()));
        $end = microtime(true);
        $time = $end - $start;
        echo "✓Async Time: " . round($time, 2) . "s\n";
    });
}


function test1Sync()
{
    return async(function ()  {
        $start = microtime(true);
        await(fileHandler()->writeFileFromGenerator('large-test1-sync.txt', (function () {
            $buffer = '';
            for ($i = 0; $i < 10_000_000; $i++) {
                $buffer .= 'chunk' . $i . "\n";
                if (strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            if ($buffer) yield $buffer;
        })()));
        $end = microtime(true);
        $time = $end - $start;
        echo "✓Sync Time: " . round($time, 2) . "s\n";
    });
}

function test2Sync()
{
    return async(function ()  {
        $start = microtime(true);
        await(fileHandler()->writeFileFromGenerator('large-test2-sync.txt', (function () {
            $buffer = '';
            for ($i = 0; $i < 10_000_000; $i++) {
                $buffer .= 'chunk' . $i . "\n";
                if (strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            if ($buffer) yield $buffer;
        })()));
        $end = microtime(true);
        $time = $end - $start;
        echo "✓Sync Time: " . round($time, 2) . "s\n";
    });
}

function test3Sync()
{
    return async(function ()  {
        $start = microtime(true);
        await(fileHandler()->writeFileFromGenerator('large-test3-sync.txt', (function () {
            $buffer = '';
            for ($i = 0; $i < 10_000_000; $i++) {
                $buffer .= 'chunk' . $i . "\n";
                if (strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            if ($buffer) yield $buffer;
        })()));
        $end = microtime(true);
        $time = $end - $start;
        echo "✓Sync Time: " . round($time, 2) . "s\n";
    });
}

function testDelay()
{
    return async(function ()  {
        $start = microtime(true);
        await(delay(1));
        $end = microtime(true);
        $time = $end - $start;
        echo "✓Delay Time: " . round($time, 2) . "s\n";
    });
}

$start = microtime(true);
await(all([testDelay(), test1Async(), testDelay(), test2Async(), testDelay(), test3Async()]));
$end = microtime(true);
$time = $end - $start;
echo "✓async Total Time: " . round($time, 2) . "s\n";

// $start1 = microtime(true);
// test1Sync()->await(false);
// test2Sync()->await(false);
// test3Sync()->await(false);
// $end1 = microtime(true);
// $time1 = $end1 - $start1;
// echo "✓sync Total Time: " . round($time1, 2) . "s\n";
