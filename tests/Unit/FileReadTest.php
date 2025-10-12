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

describe('File::read()', function () {
    it('reads file successfully', function () {
        $path = TestHelper::getTestPath('test.txt');
        $content = 'Hello, World!';
        file_put_contents($path, $content);

        $result = File::read($path)->await();

        expect($result)->toBe($content);
    });

    it('reads file with offset', function () {
        $path = TestHelper::getTestPath('test.txt');
        file_put_contents($path, 'Hello, World!');

        $result = File::read($path, ['offset' => 7])->await();

        expect($result)->toBe('World!');
    });

    it('reads file with length', function () {
        $path = TestHelper::getTestPath('test.txt');
        file_put_contents($path, 'Hello, World!');

        $result = File::read($path, ['length' => 5])->await();

        expect($result)->toBe('Hello');
    });

    it('reads file with offset and length', function () {
        $path = TestHelper::getTestPath('test.txt');
        file_put_contents($path, 'Hello, World!');

        $result = File::read($path, ['offset' => 7, 'length' => 5])->await();

        expect($result)->toBe('World');
    });

    it('throws exception for non-existent file', function () {
        $path = TestHelper::getTestPath('nonexistent.txt');

        File::read($path)->await();
    })->throws(FileNotFoundException::class);

    it('reads empty file', function () {
        $path = TestHelper::getTestPath('empty.txt');
        file_put_contents($path, '');

        $result = File::read($path)->await();

        expect($result)->toBe('');
    });

    it('reads large file', function () {
        $path = TestHelper::getTestPath('large.txt');
        $content = str_repeat('A', 1024 * 1024);
        file_put_contents($path, $content);

        $result = File::read($path)->await();

        expect($result)->toBe($content);
    });
});
