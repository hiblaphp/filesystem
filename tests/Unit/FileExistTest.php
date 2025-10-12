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

describe('File::exists()', function () {
    it('returns true for existing file', function () {
        $path = TestHelper::getTestPath('exists.txt');
        file_put_contents($path, 'content');

        $exists = File::exists($path)->await();

        expect($exists)->toBeTrue();
    });

    it('returns false for non-existent file', function () {
        $path = TestHelper::getTestPath('nonexistent.txt');

        $exists = File::exists($path)->await();

        expect($exists)->toBeFalse();
    });

    it('returns true for existing directory', function () {
        $path = TestHelper::getTestPath('subdir');
        mkdir($path);

        $exists = File::exists($path)->await();

        expect($exists)->toBeTrue();
    });

    it('returns false for empty path', function () {
        $exists = File::exists('')->await();

        expect($exists)->toBeFalse();
    });
});
