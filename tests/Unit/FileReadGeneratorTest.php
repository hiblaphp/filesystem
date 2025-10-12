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

describe('File::readFromGenerator()', function () {
    it('reads file as generator chunks', function () {
        $path = TestHelper::getTestPath('test.txt');
        $content = str_repeat('ABC', 1000);
        file_put_contents($path, $content);

        $generator = File::readFromGenerator($path, ['chunk_size' => 100])->await();

        $chunks = [];
        foreach ($generator as $chunk) {
            $chunks[] = $chunk;
        }

        expect(implode('', $chunks))->toBe($content);
        expect(count($chunks))->toBeGreaterThan(1);
    });

    it('can be cancelled during reading', function () {
        $path = TestHelper::getTestPath('large.txt');
        file_put_contents($path, str_repeat('X', 10 * 1024 * 1024)); // 10MB

        $promise = File::readFromGenerator($path, ['chunk_size' => 1024]);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });

    it('respects offset and length options', function () {
        $path = TestHelper::getTestPath('test.txt');
        file_put_contents($path, '0123456789ABCDEF');

        $generator = File::readFromGenerator($path, [
            'chunk_size' => 4,
            'offset' => 5,
            'length' => 8,
        ])->await();

        $result = '';
        foreach ($generator as $chunk) {
            $result .= $chunk;
        }

        expect($result)->toBe('56789ABC');
    });

    it('handles single chunk read', function () {
        $path = TestHelper::getTestPath('small.txt');
        $content = 'Small';
        file_put_contents($path, $content);

        $generator = File::readFromGenerator($path, ['chunk_size' => 1024])->await();

        $chunks = iterator_to_array($generator);

        expect(count($chunks))->toBe(1);
        expect($chunks[0])->toBe($content);
    });
});
