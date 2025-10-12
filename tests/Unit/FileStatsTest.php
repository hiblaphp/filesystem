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

describe('File::getStats()', function () {
    it('returns file statistics', function () {
        $path = TestHelper::getTestPath('stats.txt');
        $content = 'Test content';
        file_put_contents($path, $content);

        $stats = File::getStats($path)->await();

        expect($stats)->toBeArray();
        expect($stats['size'])->toBe(strlen($content));
        expect($stats)->toHaveKeys(['size', 'atime', 'mtime', 'ctime']);
    });

    it('throws exception for non-existent file', function () {
        $path = TestHelper::getTestPath('nonexistent.txt');

        File::getStats($path)->await();
    })->throws(FileNotFoundException::class);

    it('returns stats for directory', function () {
        $path = TestHelper::getTestPath('dir');
        mkdir($path);

        $stats = File::getStats($path)->await();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKey('size');
    });
});
