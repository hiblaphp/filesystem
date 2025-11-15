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

        $chunkGenerator = function () use ($content): ?string {
            static $sent = false;
            if (!$sent) {
                $sent = true;
                return $content;
            }
            return null;
        };

        $bytesWritten = File::writeStream($path, $chunkGenerator)->await();

        expect($bytesWritten)->toBe(strlen($content));
        expect(file_get_contents($path))->toBe($content);
    });

    it('can be cancelled and removes partial file', function () {
        $path = TestHelper::getTestPath('output.txt');
        $content = str_repeat('X', 10 * 1024 * 1024); 

        $chunkGenerator = function () use ($content): ?string {
            static $sent = false;
            if (!$sent) {
                $sent = true;
                return $content;
            }
            return null;
        };

        $promise = File::writeStream($path, $chunkGenerator);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();

        usleep(100000);

        expect(file_exists($path))->toBeFalse();
    });

    it('creates directories when option enabled', function () {
        $path = TestHelper::getTestPath('deep/nested/dir/file.txt');

        $chunkGenerator = function (): ?string {
            static $sent = false;
            if (!$sent) {
                $sent = true;
                return 'Content';
            }
            return null;
        };

        File::writeStream($path, $chunkGenerator, ['create_directories' => true])->await();

        expect(file_exists($path))->toBeTrue();
    });
});