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

describe('Partial Failures', function () {
    it('handles concurrent operations with mixed results', function () {
        $files = IntegrationTestHelper::createTestFiles([
            'exists.txt' => 'content',
        ]);

        $results = Promise::allSettled([
            File::read($files['exists.txt']),
            File::read(IntegrationTestHelper::getTestPath('missing.txt')),
            File::write(IntegrationTestHelper::getTestPath('output.txt'), 'data'),
        ])->await();

        $successful = array_filter($results, fn ($r) => $r['status'] === 'fulfilled');
        $failed = array_filter($results, fn ($r) => $r['status'] === 'rejected');

        expect(count($successful))->toBe(2);
        expect(count($failed))->toBe(1);
    });
});

describe('Transactional Operations', function () {
    it('implements rollback on failure', function () {
        $files = IntegrationTestHelper::createTestFiles([
            'file1.txt' => 'original1',
            'file2.txt' => 'original2',
        ]);

        $backup1 = IntegrationTestHelper::getTestPath('file1.txt.bak');
        $backup2 = IntegrationTestHelper::getTestPath('file2.txt.bak');

        try {
            // Backup
            File::copy($files['file1.txt'], $backup1)->await();
            File::copy($files['file2.txt'], $backup2)->await();

            // Modify
            File::write($files['file1.txt'], 'modified1')->await();
            File::write($files['file2.txt'], 'modified2')->await();

            // Simulate error
            throw new Exception('Transaction failed');
        } catch (Exception $e) {
            // Rollback
            File::copy($backup1, $files['file1.txt'])->await();
            File::copy($backup2, $files['file2.txt'])->await();
        }

        expect(file_get_contents($files['file1.txt']))->toBe('original1');
        expect(file_get_contents($files['file2.txt']))->toBe('original2');
    });
});