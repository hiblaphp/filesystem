<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\Exceptions\FileAlreadyExistsException;
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

describe('File::createDirectory()', function () {
    it('creates directory successfully', function () {
        $path = TestHelper::getTestPath('newdir');

        $result = File::createDirectory($path)->await();
        expect($result)->toBeTrue();
        expect(is_dir($path))->toBeTrue();
    });

    it('creates nested directories with recursive option', function () {
        $path = TestHelper::getTestPath('parent/child/grandchild');

        File::createDirectory($path, ['recursive' => true])->await();

        expect(is_dir($path))->toBeTrue();
    });

    it('sets directory permissions', function () {
        $path = TestHelper::getTestPath('perms');

        File::createDirectory($path, ['mode' => 0755])->await();

        expect(is_dir($path))->toBeTrue();
    });

    it('throws exception when directory exists', function () {
        $path = TestHelper::getTestPath('existing');
        mkdir($path);

        File::createDirectory($path)->await();
    })->throws(FileAlreadyExistsException::class);
});

describe('File::removeDirectory()', function () {
    it('removes empty directory', function () {
        $path = TestHelper::getTestPath('removeme');
        mkdir($path);

        $result = File::removeDirectory($path)->await();

        expect($result)->toBeTrue();
        expect(is_dir($path))->toBeFalse();
    });

    it('removes non-empty directory recursively', function () {
        $path = TestHelper::getTestPath('parent');
        mkdir($path . '/child', 0777, true);
        file_put_contents($path . '/child/file.txt', 'content');

        File::removeDirectory($path)->await();

        expect(is_dir($path))->toBeFalse();
    });

    it('throws exception for non-existent directory', function () {
        $path = TestHelper::getTestPath('nonexistent');

        File::removeDirectory($path)->await();
    })->throws(FileNotFoundException::class);
});
