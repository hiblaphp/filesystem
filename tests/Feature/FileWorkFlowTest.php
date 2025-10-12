<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\File;
use Tests\Feature\Helpers\FeatureTestHelper;

beforeEach(function () {
    FeatureTestHelper::setupTestDirectory();
    File::reset();
    EventLoop::reset();
});

afterEach(function () {
    FeatureTestHelper::cleanupTestDirectory();
});


describe('File processing workflows', function () {
    it('reads, transforms, and writes file', function () {
        $source = FeatureTestHelper::getTestPath('input.txt');
        $dest = FeatureTestHelper::getTestPath('output.txt');
        file_put_contents($source, 'hello world');

        $content = File::read($source)->await();
        $transformed = strtoupper($content);
        File::write($dest, $transformed)->await();

        expect(file_get_contents($dest))->toBe('HELLO WORLD');
    });

    it('processes large file in chunks with generator', function () {
        $source = FeatureTestHelper::getTestPath('large.txt');
        $dest = FeatureTestHelper::getTestPath('processed.txt');

        $lines = array_fill(0, 10000, 'Line of text');
        file_put_contents($source, implode("\n", $lines));

        $lineGenerator = File::readLines($source)->await();

        $transformedGenerator = (function () use ($lineGenerator) {
            foreach ($lineGenerator as $line) {
                yield strtoupper($line) . "\n";
            }
        })();

        File::writeFromGenerator($dest, $transformedGenerator, ['buffer_size' => 8192])->await();

        $result = file_get_contents($dest);
        expect($result)->toContain('LINE OF TEXT');
    });

    it('filters file content line by line', function () {
        $source = FeatureTestHelper::getTestPath('log.txt');
        $dest = FeatureTestHelper::getTestPath('errors.txt');

        file_put_contents($source, "INFO: Starting\nERROR: Failed\nINFO: Done\nERROR: Crash");

        $lineGenerator = File::readLines($source)->await();

        $errorGenerator = (function () use ($lineGenerator) {
            foreach ($lineGenerator as $line) {
                if (str_starts_with($line, 'ERROR:')) {
                    yield $line . "\n";
                }
            }
        })();

        File::writeFromGenerator($dest, $errorGenerator)->await();

        $errors = file_get_contents($dest);
        expect($errors)->toContain('ERROR: Failed');
        expect($errors)->toContain('ERROR: Crash');
        expect($errors)->not->toContain('INFO:');
    });

    it('backs up file before modifying', function () {
        $original = FeatureTestHelper::getTestPath('data.txt');
        $backup = FeatureTestHelper::getTestPath('data.txt.bak');

        file_put_contents($original, 'Important data');

        File::copy($original, $backup)->await();
        File::write($original, 'Modified data')->await();

        expect(file_get_contents($backup))->toBe('Important data');
        expect(file_get_contents($original))->toBe('Modified data');
    });
});

describe('Promise chaining workflows', function () {
    it('chains multiple file operations', function () {
        $source = FeatureTestHelper::getTestPath('source.txt');
        $dest = FeatureTestHelper::getTestPath('dest.txt');
        file_put_contents($source, '  trim me  ');

        $result = File::read($source)
            ->then(fn ($content) => trim($content))
            ->then(fn ($content) => strtoupper($content))
            ->then(fn ($content) => File::write($dest, $content))
            ->await();

        expect(file_get_contents($dest))->toBe('TRIM ME');
    });

    it('handles errors gracefully in chain', function () {
        $result = File::read(FeatureTestHelper::getTestPath('nonexistent.txt'))
            ->then(fn ($content) => strtoupper($content))
            ->catch(fn ($error) => 'DEFAULT CONTENT')
            ->then(fn ($content) => File::write(FeatureTestHelper::getTestPath('output.txt'), $content))
            ->await();

        expect(file_get_contents(FeatureTestHelper::getTestPath('output.txt')))->toBe('DEFAULT CONTENT');
    });

    it('chains file existence check before reading', function () {
        $path = FeatureTestHelper::getTestPath('check.txt');
        file_put_contents($path, 'content');

        $result = File::exists($path)
            ->then(function ($exists) use ($path) {
                if ($exists) {
                    return File::read($path);
                }

                return 'File not found';
            })
            ->await();

        expect($result)->toBe('content');
    });

    it('chains copy and verify operations', function () {
        $source = FeatureTestHelper::getTestPath('source.txt');
        $dest = FeatureTestHelper::getTestPath('dest.txt');
        $content = 'Test content';
        file_put_contents($source, $content);

        $verified = File::copy($source, $dest)
            ->then(fn () => File::exists($dest))
            ->then(fn ($exists) => $exists ? File::read($dest) : null)
            ->await();

        expect($verified)->toBe($content);
    });
});

describe('Cancellation workflows', function () {
    it('cancels long-running read operation', function () {
        $path = FeatureTestHelper::getTestPath('huge.txt');
        file_put_contents($path, str_repeat('X', 50 * 1024 * 1024)); 

        $promise = File::readStream($path);

        FeatureTestHelper::waitForFileSystem(10);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });

    it('cancels write operation and cleans up partial file', function () {
        $path = FeatureTestHelper::getTestPath('cancelled.txt');
        $data = str_repeat('Data', 1000000);

        $promise = File::writeStream($path, $data);
        $promise->cancel();

        FeatureTestHelper::waitForFileSystem(100);

        expect($promise->isCancelled())->toBeTrue();
        expect(file_exists($path))->toBeFalse();
    });

    it('cancels file copy and removes destination', function () {
        $source = FeatureTestHelper::getTestPath('source.txt');
        $dest = FeatureTestHelper::getTestPath('dest.txt');
        file_put_contents($source, str_repeat('X', 20 * 1024 * 1024)); // 20MB

        $promise = File::copyStream($source, $dest);
        FeatureTestHelper::waitForFileSystem(10);
        $promise->cancel();

        FeatureTestHelper::waitForFileSystem(100);

        expect($promise->isCancelled())->toBeTrue();
        expect(file_exists($dest))->toBeFalse();
        expect(file_exists($source))->toBeTrue(); // Source unchanged
    });

    it('maintains cancellability through promise chain', function () {
        $path = FeatureTestHelper::getTestPath('large.txt');
        file_put_contents($path, str_repeat('X', 10 * 1024 * 1024));

        $promise = File::readStream($path)
            ->then(fn ($content) => strlen($content))
            ->then(fn ($length) => "Length: $length");

        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });
});

describe('Directory management workflows', function () {
    it('creates nested project structure', function () {
        $base = FeatureTestHelper::getTestPath('project');

        File::createDirectory("$base/src", ['recursive' => true])->await();
        File::createDirectory("$base/tests", ['recursive' => true])->await();
        File::createDirectory("$base/config", ['recursive' => true])->await();

        File::write("$base/src/index.php", '<?php')->await();
        File::write("$base/tests/test.php", '<?php')->await();
        File::write("$base/config/app.php", '<?php return [];')->await();

        expect(is_dir("$base/src"))->toBeTrue();
        expect(is_dir("$base/tests"))->toBeTrue();
        expect(is_dir("$base/config"))->toBeTrue();
        expect(file_exists("$base/src/index.php"))->toBeTrue();
    });

    it('backs up directory contents', function () {
        $original = FeatureTestHelper::getTestPath('original');
        $backup = FeatureTestHelper::getTestPath('backup');

        mkdir($original);
        file_put_contents("$original/file1.txt", 'Content 1');
        file_put_contents("$original/file2.txt", 'Content 2');

        File::createDirectory($backup)->await();
        File::copy("$original/file1.txt", "$backup/file1.txt")->await();
        File::copy("$original/file2.txt", "$backup/file2.txt")->await();

        expect(file_get_contents("$backup/file1.txt"))->toBe('Content 1');
        expect(file_get_contents("$backup/file2.txt"))->toBe('Content 2');
    });

    it('cleans up temporary files', function () {
        $temp = FeatureTestHelper::getTestPath('temp');
        mkdir($temp);

        file_put_contents("$temp/temp1.txt", 'temp');
        file_put_contents("$temp/temp2.txt", 'temp');
        file_put_contents("$temp/temp3.txt", 'temp');

        File::removeDirectory($temp)->await();

        expect(is_dir($temp))->toBeFalse();
    });
});

describe('Data processing workflows', function () {
    it('processes CSV file line by line', function () {
        $csv = FeatureTestHelper::getTestPath('data.csv');
        file_put_contents($csv, "name,age\nAlice,30\nBob,25\nCharlie,35");

        $lineGenerator = File::readLines($csv, ['skip_empty' => true])->await();

        $records = [];
        $isHeader = true;
        foreach ($lineGenerator as $line) {
            if ($isHeader) {
                $isHeader = false;
                continue;
            }
            [$name, $age] = explode(',', $line);
            $records[] = ['name' => $name, 'age' => (int)$age];
        }

        expect(count($records))->toBe(3);
        expect($records[0]['name'])->toBe('Alice');
        expect($records[0]['age'])->toBe(30);
    });

    it('generates report from multiple files', function () {
        $file1 = FeatureTestHelper::getTestPath('data1.txt');
        $file2 = FeatureTestHelper::getTestPath('data2.txt');
        $report = FeatureTestHelper::getTestPath('report.txt');

        file_put_contents($file1, "Item A: 100\nItem B: 200");
        file_put_contents($file2, "Item C: 150\nItem D: 250");

        $content1 = File::read($file1)->await();
        $content2 = File::read($file2)->await();

        $combined = "=== Report ===\n\nFile 1:\n$content1\n\nFile 2:\n$content2\n";

        File::write($report, $combined)->await();

        $result = file_get_contents($report);
        expect($result)->toContain('=== Report ===');
        expect($result)->toContain('Item A: 100');
        expect($result)->toContain('Item C: 150');
    });

    it('splits large file into smaller chunks', function () {
        $source = FeatureTestHelper::getTestPath('large.txt');
        $lines = array_fill(0, 1000, 'Line of data');
        file_put_contents($source, implode("\n", $lines));

        $lineGenerator = File::readLines($source)->await();

        $chunkSize = 100;
        $chunkNum = 0;
        $buffer = [];

        foreach ($lineGenerator as $line) {
            $buffer[] = $line;

            if (count($buffer) >= $chunkSize) {
                $chunkFile = FeatureTestHelper::getTestPath("chunk_$chunkNum.txt");
                File::write($chunkFile, implode("\n", $buffer))->await();
                $buffer = [];
                $chunkNum++;
            }
        }

        if (! empty($buffer)) {
            $chunkFile = FeatureTestHelper::getTestPath("chunk_$chunkNum.txt");
            File::write($chunkFile, implode("\n", $buffer))->await();
        }

        expect(file_exists(FeatureTestHelper::getTestPath('chunk_0.txt')))->toBeTrue();
        expect(file_exists(FeatureTestHelper::getTestPath('chunk_9.txt')))->toBeTrue();
    });
});

describe('Error recovery workflows', function () {
    it('retries failed operation', function () {
        $path = FeatureTestHelper::getTestPath('flaky.txt');
        $attempts = 0;
        $maxAttempts = 3;

        $tryRead = function () use ($path, &$attempts, $maxAttempts) {
            return File::read($path)
                ->catch(function ($error) use (&$attempts, $maxAttempts, $path) {
                    $attempts++;
                    if ($attempts < $maxAttempts) {
                        // Create file on retry
                        file_put_contents($path, 'Success on retry');

                        return File::read($path)->await();
                    }

                    throw $error;
                });
        };

        $result = $tryRead()->await();

        expect($result)->toBe('Success on retry');
        expect($attempts)->toBeGreaterThan(0);
    });

    it('falls back to default when file missing', function () {
        $config = File::read(FeatureTestHelper::getTestPath('config.json'))
            ->catch(fn () => '{"default": true}')
            ->then(fn ($json) => json_decode($json, true))
            ->await();

        expect($config)->toBeArray();
        expect($config['default'])->toBeTrue();
    });
});

describe('Performance optimization workflows', function () {
    it('uses buffered writes for many small chunks', function () {
        $path = FeatureTestHelper::getTestPath('buffered.txt');

        $startTime = microtime(true);

        $generator = function () {
            for ($i = 0; $i < 10000; $i++) {
                yield "Line $i\n";
            }
        };

        File::writeFromGenerator($path, $generator(), ['buffer_size' => 8192])->await();

        $duration = microtime(true) - $startTime;

        expect(file_exists($path))->toBeTrue();
        expect($duration)->toBeLessThan(5); // Should complete in reasonable time
    });

    it('processes large file without loading into memory', function () {
        $source = FeatureTestHelper::getTestPath('huge.txt');
        $dest = FeatureTestHelper::getTestPath('counted.txt');

        // Create large file
        $generator = function () {
            for ($i = 0; $i < 100000; $i++) {
                yield "Line $i\n";
            }
        };
        File::writeFromGenerator($source, $generator(), ['buffer_size' => 8192])->await();

        $lineGenerator = File::readLines($source)->await();
        $lineCount = 0;

        foreach ($lineGenerator as $line) {
            $lineCount++;
        }

        File::write($dest, "Total lines: $lineCount")->await();

        expect($lineCount)->toBe(100000);
        expect(file_get_contents($dest))->toBe('Total lines: 100000');
    });
});