<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\Exceptions\FileWriteException;
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

describe('File::write()', function () {
    it('writes content to file', function () {
        $path = TestHelper::getTestPath('output.txt');
        $content = 'Test content';

        $bytesWritten = File::write($path, $content)->await();

        expect($bytesWritten)->toBe(strlen($content));
        expect(file_get_contents($path))->toBe($content);
    });

    it('overwrites existing file', function () {
        $path = TestHelper::getTestPath('output.txt');
        file_put_contents($path, 'Old content');

        File::write($path, 'New content')->await();

        expect(file_get_contents($path))->toBe('New content');
    });

    it('creates directories when option enabled', function () {
        $path = TestHelper::getTestPath('subdir/nested/file.txt');

        File::write($path, 'Content', ['create_directories' => true])->await();

        expect(file_exists($path))->toBeTrue();
        expect(file_get_contents($path))->toBe('Content');
    });

    it('writes empty content', function () {
        $path = TestHelper::getTestPath('empty.txt');

        File::write($path, '')->await();

        expect(file_exists($path))->toBeTrue();
        expect(file_get_contents($path))->toBe('');
    });

    it('writes large content', function () {
        $path = TestHelper::getTestPath('large.txt');
        $content = str_repeat('X', 5 * 1024 * 1024); // 5MB

        File::write($path, $content)->await();

        expect(filesize($path))->toBe(strlen($content));
    });

    it('throws exception for invalid path', function () {
        // Use a null byte which is invalid on all platforms
        $path = TestHelper::getTestPath("invalid\0path/file.txt");

        File::write($path, 'content')->await();
    })->throws(FileWriteException::class);
});
