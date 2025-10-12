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

describe('File::append()', function () {
    it('appends content to existing file', function () {
        $path = TestHelper::getTestPath('append.txt');
        file_put_contents($path, 'First ');

        File::append($path, 'Second')->await();

        expect(file_get_contents($path))->toBe('First Second');
    });

    it('creates file if it does not exist', function () {
        $path = TestHelper::getTestPath('new.txt');

        File::append($path, 'Content')->await();

        expect(file_exists($path))->toBeTrue();
        expect(file_get_contents($path))->toBe('Content');
    });

    it('appends multiple times', function () {
        $path = TestHelper::getTestPath('multi.txt');

        File::append($path, 'A')->await();
        File::append($path, 'B')->await();
        File::append($path, 'C')->await();

        expect(file_get_contents($path))->toBe('ABC');
    });

    it('appends empty string', function () {
        $path = TestHelper::getTestPath('empty_append.txt');
        file_put_contents($path, 'Content');

        File::append($path, '')->await();

        expect(file_get_contents($path))->toBe('Content');
    });
});
