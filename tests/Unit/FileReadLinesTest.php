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

describe('File::readLines()', function () {
    it('reads file line by line', function () {
        $path = TestHelper::getTestPath('lines.txt');
        $lines = ['Line 1', 'Line 2', 'Line 3'];
        file_put_contents($path, implode("\n", $lines));

        $generator = File::readLines($path)->await();

        $result = [];
        foreach ($generator as $line) {
            $result[] = $line;
        }

        expect($result)->toBe($lines);
    });

    it('trims lines when option enabled', function () {
        $path = TestHelper::getTestPath('lines.txt');
        file_put_contents($path, "  Line 1  \n  Line 2  \n");

        $generator = File::readLines($path, ['trim' => true])->await();

        $result = [];
        foreach ($generator as $line) {
            $result[] = $line;
        }

        expect($result)->toBe(['Line 1', 'Line 2']);
    });

    it('skips empty lines when option enabled', function () {
        $path = TestHelper::getTestPath('lines.txt');
        file_put_contents($path, "Line 1\n\nLine 2\n\n\nLine 3\n");

        $generator = File::readLines($path, ['skip_empty' => true])->await();

        $result = [];
        foreach ($generator as $line) {
            $result[] = $line;
        }

        expect($result)->toBe(['Line 1', 'Line 2', 'Line 3']);
    });

    it('can be cancelled during reading', function () {
        $path = TestHelper::getTestPath('many_lines.txt');
        $lines = array_fill(0, 10000, 'Line');
        file_put_contents($path, implode("\n", $lines));

        $promise = File::readLines($path);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });

    it('handles different line endings', function () {
        $path = TestHelper::getTestPath('mixed_endings.txt');
        file_put_contents($path, "Line 1\rLine 2\r\nLine 3\n");

        $generator = File::readLines($path)->await();

        $result = iterator_to_array($generator);

        expect(count($result))->toBeGreaterThanOrEqual(3);
    });
});
