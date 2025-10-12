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

describe('File::delete()', function () {
    it('deletes existing file', function () {
        $path = TestHelper::getTestPath('delete.txt');
        file_put_contents($path, 'content');

        $result = File::delete($path)->await();

        expect($result)->toBeTrue();
        expect(file_exists($path))->toBeFalse();
    });

    it('throws exception for non-existent file', function () {
        $path = TestHelper::getTestPath('nonexistent.txt');

        File::delete($path)->await();
    })->throws(FileNotFoundException::class);

    it('deletes empty file', function () {
        $path = TestHelper::getTestPath('empty.txt');
        file_put_contents($path, '');

        $result = File::delete($path)->await();

        expect($result)->toBeTrue();
        expect(file_exists($path))->toBeFalse();
    });
});
