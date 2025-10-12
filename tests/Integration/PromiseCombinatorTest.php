<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\File;
use Hibla\Promise\Promise;
use Tests\Integration\Helpers\IntegrationTestHelper;

beforeEach(function () {
    IntegrationTestHelper::setupTestDirectory();
    File::reset();
    EventLoop::reset();
});

afterEach(function () {
    IntegrationTestHelper::cleanupTestDirectory();
});

describe('Promise::all() integration', function () {
    it('reads multiple files concurrently', function () {
        $files = IntegrationTestHelper::createTestFiles([
            'file1.txt' => 'Content 1',
            'file2.txt' => 'Content 2',
            'file3.txt' => 'Content 3',
        ]);

        $results = Promise::all([
            File::read($files['file1.txt']),
            File::read($files['file2.txt']),
            File::read($files['file3.txt']),
        ])->await();

        expect($results)->toHaveCount(3);
        expect($results[0])->toBe('Content 1');
        expect($results[1])->toBe('Content 2');
        expect($results[2])->toBe('Content 3');
    });

    it('writes multiple files concurrently', function () {
        $files = [
            'output1.txt' => 'Data 1',
            'output2.txt' => 'Data 2',
            'output3.txt' => 'Data 3',
            'output4.txt' => 'Data 4',
            'output5.txt' => 'Data 5',
        ];

        $promises = [];
        foreach ($files as $filename => $content) {
            $promises[] = File::write(IntegrationTestHelper::getTestPath($filename), $content);
        }

        Promise::all($promises)->await();

        foreach ($files as $filename => $content) {
            $path = IntegrationTestHelper::getTestPath($filename);
            expect(file_get_contents($path))->toBe($content);
        }
    });
});

describe('Promise::race() integration', function () {
    it('gets first file read to complete', function () {
        $files = IntegrationTestHelper::createTestFiles([
            'fast.txt' => 'Fast content',
            'slow.txt' => str_repeat('Slow', 100000),
        ]);

        $result = Promise::race([
            File::read($files['fast.txt']),
            File::read($files['slow.txt']),
        ])->await();

        expect($result)->toBe('Fast content');
    });
});

describe('Promise::allSettled() integration', function () {
    it('handles mixed success and failure', function () {
        $existing = IntegrationTestHelper::createTestFiles([
            'exists.txt' => 'content',
        ]);

        $results = Promise::allSettled([
            File::read($existing['exists.txt']),
            File::read(IntegrationTestHelper::getTestPath('missing1.txt')),
            File::read(IntegrationTestHelper::getTestPath('missing2.txt')),
        ])->await();

        expect($results)->toHaveCount(3);
        expect($results[0]['status'])->toBe('fulfilled');
        expect($results[0]['value'])->toBe('content');
        expect($results[1]['status'])->toBe('rejected');
        expect($results[2]['status'])->toBe('rejected');
    });
});

describe('Promise::concurrent() integration', function () {
    it('processes files with controlled concurrency', function () {
        $files = [];
        for ($i = 0; $i < 20; $i++) {
            $files["file_$i.txt"] = "Content $i";
        }

        $paths = IntegrationTestHelper::createTestFiles($files);
        $tasks = array_map(fn($path) => fn() => File::read($path), array_values($paths));

        $results = Promise::concurrent($tasks, 5)->await();

        expect($results)->toHaveCount(20);
        expect($results[0])->toBe('Content 0');
        expect($results[19])->toBe('Content 19');
    });
});

describe('Promise::batch() integration', function () {
    it('processes files in batches', function () {
        $files = [];
        for ($i = 0; $i < 30; $i++) {
            $files["batch_$i.txt"] = "Batch $i";
        }

        $paths = IntegrationTestHelper::createTestFiles($files);
        $tasks = array_map(fn($path) => fn() => File::read($path), array_values($paths));

        $results = Promise::batch($tasks, 10)->await();

        expect($results)->toHaveCount(30);
    });
});