<?php

use Hibla\Filesystem\File;

require 'vendor/autoload.php';

$promise = File::writeStream("test.txt", str_repeat('a', 100_000_000));

delay(0.1)->then(function () use ($promise) {
    $promise->cancel();
});

$promise->then(function () {
 echo "hello";
});
