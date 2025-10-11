<?php

use Hibla\Filesystem\File;
use Hibla\Promise\Promise;

use function Hibla\async;
use function Hibla\await;

require 'vendor/autoload.php';


$readFileAsync1 = async(function () {
    $startTime = microtime(true);
    await(File::write('test1.txt', str_repeat('a', 10000000)));
    $endTime = microtime(true);
    echo 'read memory time: ' . ($endTime - $startTime) . ' seconds' . "\n";
});

$readFileAsync2 = async(function () {
    $startTime = microtime(true);
    await(File::write('test2.txt', str_repeat('a', 10000000)));
    $endTime = microtime(true);
    echo 'read memory time: ' . ($endTime - $startTime) . ' seconds' . "\n";
});

$readFileAsync3 = async(function () {
    $startTime = microtime(true);
    await(File::write('test3.txt', str_repeat('a', 10000000)));
    $endTime = microtime(true);
    echo 'read memory time: ' . ($endTime - $startTime) . ' seconds' . "\n";
});

$startTime = microtime(true);
Promise::all([
    $readFileAsync1,
    $readFileAsync2,
    $readFileAsync3,
])->await();
$endTime = microtime(true);
echo 'total seconds: ' . ($endTime - $startTime) . ' seconds' . "\n";

$syncWrite1 = function () {
    $startTime = microtime(true);
    file_put_contents('test4.txt', str_repeat('a', 10000000));
    $endTime = microtime(true);
    echo 'sync read memory time: ' . ($endTime - $startTime) . ' seconds' . "\n";
};

$syncWrite2 = function () {
    $startTime = microtime(true);
    file_put_contents('test5.txt', str_repeat('a', 10000000));
    $endTime = microtime(true);
    echo 'sync read memory time: ' . ($endTime - $startTime) . ' seconds' . "\n";
};

$syncWrite3 = function () {
    $startTime = microtime(true);
    file_put_contents('test6.txt', str_repeat('a', 10000000));
    $endTime = microtime(true);
    echo 'sync read memory time: ' . ($endTime - $startTime) . ' seconds' . "\n";
};


$syncTime = microtime(true);
$syncWrite1();
$syncWrite2();
$syncWrite3();
$syncEndTime = microtime(true);
echo 'sync total seconds: ' . ($syncEndTime - $syncTime) . ' seconds' . "\n";
