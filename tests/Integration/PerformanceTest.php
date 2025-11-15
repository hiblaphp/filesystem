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