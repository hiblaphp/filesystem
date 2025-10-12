<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\Exceptions\FileCopyException;
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

describe('File::copy()', function () {
    it('copies file successfully', function () {
        $source = TestHelper::getTestPath('source.txt');
        $dest = TestHelper::getTestPath('dest.txt');
        $content = 'Copy me';
        file_put_contents($source, $content);

        $result = File::copy($source, $dest)->await();

        expect($result)->toBeTrue();
        expect(file_get_contents($dest))->toBe($content);
        expect(file_exists($source))->toBeTrue();
    });

    it('overwrites destination if exists', function () {
        $source = TestHelper::getTestPath('source.txt');
        $dest = TestHelper::getTestPath('dest.txt');
        file_put_contents($source, 'New content');
        file_put_contents($dest, 'Old content');

        File::copy($source, $dest)->await();

        expect(file_get_contents($dest))->toBe('New content');
    });

    it('throws exception for non-existent source', function () {
        $source = TestHelper::getTestPath('nonexistent.txt');
        $dest = TestHelper::getTestPath('dest.txt');

        File::copy($source, $dest)->await();
    })->throws(FileCopyException::class);

    it('copies empty file', function () {
        $source = TestHelper::getTestPath('empty.txt');
        $dest = TestHelper::getTestPath('dest.txt');
        file_put_contents($source, '');

        File::copy($source, $dest)->await();

        expect(file_exists($dest))->toBeTrue();
        expect(filesize($dest))->toBe(0);
    });
});
