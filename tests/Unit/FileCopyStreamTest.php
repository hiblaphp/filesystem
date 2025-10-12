<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\File;
use Tests\Unit\Helpers\TestHelper;

beforeEach(function () {
    TestHelper::setupTestDirectory();
    File::reset();
    EventLoop::reset();
});

afterEach(function () {
    TestHelper::cleanupTestDirectory();
});

describe('File::copyStream()', function () {
    it('copies file using streaming', function () {
        $source = TestHelper::getTestPath('source.txt');
        $dest = TestHelper::getTestPath('dest.txt');
        $content = str_repeat('Copy', 1000);
        file_put_contents($source, $content);

        $result = File::copyStream($source, $dest)->await();

        expect($result)->toBeTrue();
        expect(file_get_contents($dest))->toBe($content);
    });

    it('can be cancelled and removes partial destination', function () {
        $source = TestHelper::getTestPath('large.txt');
        $dest = TestHelper::getTestPath('dest.txt');
        file_put_contents($source, str_repeat('X', 10 * 1024 * 1024));

        $promise = File::copyStream($source, $dest);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();

        usleep(100000);

        expect(file_exists($dest))->toBeFalse();
    });
});
