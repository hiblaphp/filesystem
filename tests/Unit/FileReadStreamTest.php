<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\Exceptions\FileNotFoundException;
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

describe('File::readStream()', function () {
    it('reads file using streaming', function () {
        $path = TestHelper::getTestPath('test.txt');
        $content = 'Streaming content';
        file_put_contents($path, $content);

        $result = File::readStream($path)->await();

        expect($result)->toBe($content);
    });

    it('can be cancelled before completion', function () {
        $path = TestHelper::getTestPath('test.txt');
        file_put_contents($path, str_repeat('X', 1024 * 1024));

        $promise = File::readStream($path);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });

    it('reads with offset in streaming mode', function () {
        $path = TestHelper::getTestPath('test.txt');
        file_put_contents($path, 'Hello, World!');

        $result = File::readStream($path, ['offset' => 7])->await();

        expect($result)->toBe('World!');
    });

    it('throws exception for non-existent file', function () {
        $path = TestHelper::getTestPath('nonexistent.txt');

        File::readStream($path)->await();
    })->throws(FileNotFoundException::class);
});
