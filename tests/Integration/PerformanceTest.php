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

describe('Concurrent Operations', function () {
    it('handles many concurrent file operations', function () {
        $promises = [];
        
        for ($i = 0; $i < 100; $i++) {
            $path = IntegrationTestHelper::getTestPath("concurrent_$i.txt");
            $promises[] = File::write($path, "Content $i");
        }

        Promise::all($promises)->await();

        for ($i = 0; $i < 100; $i++) {
            $path = IntegrationTestHelper::getTestPath("concurrent_$i.txt");
            expect(file_exists($path))->toBeTrue();
        }
    });
});

describe('Large Dataset Processing', function () {
    it('processes large files efficiently', function () {
        $source = IntegrationTestHelper::getTestPath('large_dataset.txt');
        
        $generator = function () {
            for ($i = 0; $i < 50000; $i++) {
                yield "Record $i: " . str_repeat('data', 10) . "\n";
            }
        };
        
        File::writeFromGenerator($source, $generator(), ['buffer_size' => 8192])->await();

        $measured = IntegrationTestHelper::measureTime(function () use ($source) {
            $lineGenerator = File::readLines($source)->await();
            $count = 0;
            foreach ($lineGenerator as $line) {
                $count++;
            }
            
            return $count;
        });

        expect($measured['result'])->toBe(50000);
        expect($measured['duration'])->toBeLessThan(10);
    });
});

describe('Rapid Modifications', function () {
    it('handles rapid file modifications', function () {
        $file = IntegrationTestHelper::getTestPath('rapid.txt');
        file_put_contents($file, 'initial');

        for ($i = 0; $i < 50; $i++) {
            File::write($file, "Version $i")->await();
        }

        $final = file_get_contents($file);
        expect($final)->toBe('Version 49');
    });
});