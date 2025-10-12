<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\File;
use Tests\Integration\Helpers\IntegrationTestHelper;

beforeEach(function () {
    IntegrationTestHelper::setupTestDirectory();
    File::reset();
    EventLoop::reset();
});

afterEach(function () {
    IntegrationTestHelper::cleanupTestDirectory();
});

describe('Complex Chains', function () {
    it('combines read, transform, write with existence checks', function () {
        $files = IntegrationTestHelper::createTestFiles([
            'source.txt' => 'hello world',
        ]);

        $result = File::exists($files['source.txt'])
            ->then(function ($exists) use ($files) {
                if (! $exists) {
                    throw new Exception('Source not found');
                }
                
                return File::read($files['source.txt']);
            })
            ->then(fn ($content) => strtoupper($content))
            ->then(fn ($content) => File::write(IntegrationTestHelper::getTestPath('dest.txt'), $content))
            ->then(fn () => File::exists(IntegrationTestHelper::getTestPath('dest.txt')))
            ->await();

        expect($result)->toBeTrue();
        
        $dest = IntegrationTestHelper::getTestPath('dest.txt');
        expect(file_get_contents($dest))->toBe('HELLO WORLD');
    });

    it('chains operations with statistics', function () {
        $content = 'Test content for statistics';
        $file = IntegrationTestHelper::getTestPath('stats.txt');
        
        $stats = File::write($file, $content)
            ->then(fn () => File::getStats($file))
            ->then(function ($stats) use ($file) {
                return [
                    'size' => $stats['size'],
                    'exists' => file_exists($file),
                    'readable' => is_readable($file),
                ];
            })
            ->await();

        expect($stats['size'])->toBe(strlen($content));
        expect($stats['exists'])->toBeTrue();
        expect($stats['readable'])->toBeTrue();
    });
});