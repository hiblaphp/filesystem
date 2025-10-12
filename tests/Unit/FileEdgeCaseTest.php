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

describe('Edge cases', function () {
    it('handles binary file content', function () {
        $path = TestHelper::getTestPath('binary.dat');
        $binary = pack('C*', 0, 1, 2, 255, 254, 253);
        file_put_contents($path, $binary);

        $result = File::read($path)->await();

        expect($result)->toBe($binary);
    });

    it('handles unicode content', function () {
        $path = TestHelper::getTestPath('unicode.txt');
        $content = '你好世界 🌍 مرحبا العالم';
        file_put_contents($path, $content);

        $result = File::read($path)->await();

        expect($result)->toBe($content);
    });

    it('handles very long file paths', function () {
        $longName = str_repeat('a', 200) . '.txt';
        $path = TestHelper::getTestPath($longName);

        File::write($path, 'content')->await();

        expect(file_exists($path))->toBeTrue();
    });

    it('handles special characters in filename', function () {
        $path = TestHelper::getTestPath('file-with-special_chars@123.txt');

        File::write($path, 'content')->await();

        expect(file_exists($path))->toBeTrue();
    });
});
