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

describe('File::writeStream()', function () {
    it('writes content using streaming', function () {
        $path = TestHelper::getTestPath('output.txt');
        $content = 'Streamed content';

        $bytesWritten = File::writeStream($path, $content)->await();

        expect($bytesWritten)->toBe(strlen($content));
        expect(file_get_contents($path))->toBe($content);
    });

    it('can be cancelled and removes partial file', function () {
        $path = TestHelper::getTestPath('output.txt');
        $content = str_repeat('X', 10 * 1024 * 1024); // 10MB

        $promise = File::writeStream($path, $content);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();

        usleep(100000);

        expect(file_exists($path))->toBeFalse();
    });

    it('creates directories when option enabled', function () {
        $path = TestHelper::getTestPath('deep/nested/dir/file.txt');

        File::writeStream($path, 'Content', ['create_directories' => true])->await();

        expect(file_exists($path))->toBeTrue();
    });
});
