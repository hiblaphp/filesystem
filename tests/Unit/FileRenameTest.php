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

describe('File::rename()', function () {
    it('renames file successfully', function () {
        $oldPath = TestHelper::getTestPath('old.txt');
        $newPath = TestHelper::getTestPath('new.txt');
        file_put_contents($oldPath, 'content');

        $result = File::rename($oldPath, $newPath)->await();

        expect($result)->toBeTrue();
        expect(file_exists($newPath))->toBeTrue();
        expect(file_exists($oldPath))->toBeFalse();
    });

    it('moves file to different directory', function () {
        $oldPath = TestHelper::getTestPath('file.txt');
        $newDir = TestHelper::getTestPath('subdir');
        mkdir($newDir);
        $newPath = $newDir . '/file.txt';
        file_put_contents($oldPath, 'content');

        File::rename($oldPath, $newPath)->await();

        expect(file_exists($newPath))->toBeTrue();
        expect(file_exists($oldPath))->toBeFalse();
    });

    it('throws exception for non-existent source', function () {
        $oldPath = TestHelper::getTestPath('nonexistent.txt');
        $newPath = TestHelper::getTestPath('new.txt');

        File::rename($oldPath, $newPath)->await();
    })->throws(FileNotFoundException::class);
});
