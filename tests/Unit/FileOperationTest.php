<?php

use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\File;

beforeEach(function () {
    $this->testDir = sys_get_temp_dir() . '/hibla_unit_test_' . uniqid();
    mkdir($this->testDir, 0777, true);
    File::reset();
    EventLoop::reset();
});

afterEach(function () {
    cleanupTestDirectory($this->testDir);
});

function getTestPath(string $filename): string
{
    return test()->testDir . '/' . $filename;
}

function cleanupTestDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }

    rmdir($dir);
}

describe('File::read()', function () {
    it('reads file successfully', function () {
        $path = getTestPath('test.txt');
        $content = 'Hello, World!';
        file_put_contents($path, $content);

        $result = File::read($path)->await();

        expect($result)->toBe($content);
    });

    it('reads file with offset', function () {
        $path = getTestPath('test.txt');
        file_put_contents($path, 'Hello, World!');

        $result = File::read($path, ['offset' => 7])->await();

        expect($result)->toBe('World!');
    });

    it('reads file with length', function () {
        $path = getTestPath('test.txt');
        file_put_contents($path, 'Hello, World!');

        $result = File::read($path, ['length' => 5])->await();

        expect($result)->toBe('Hello');
    });

    it('reads file with offset and length', function () {
        $path = getTestPath('test.txt');
        file_put_contents($path, 'Hello, World!');

        $result = File::read($path, ['offset' => 7, 'length' => 5])->await();

        expect($result)->toBe('World');
    });

    it('throws exception for non-existent file', function () {
        $path = getTestPath('nonexistent.txt');

        File::read($path)->await();
    })->throws(RuntimeException::class);

    it('reads empty file', function () {
        $path = getTestPath('empty.txt');
        file_put_contents($path, '');

        $result = File::read($path)->await();

        expect($result)->toBe('');
    });

    it('reads large file', function () {
        $path = getTestPath('large.txt');
        $content = str_repeat('A', 1024 * 1024);
        file_put_contents($path, $content);

        $result = File::read($path)->await();

        expect($result)->toBe($content);
    });
});

describe('File::readStream()', function () {
    it('reads file using streaming', function () {
        $path = getTestPath('test.txt');
        $content = 'Streaming content';
        file_put_contents($path, $content);

        $result = File::readStream($path)->await();

        expect($result)->toBe($content);
    });

    it('can be cancelled before completion', function () {
        $path = getTestPath('test.txt');
        file_put_contents($path, str_repeat('X', 1024 * 1024)); // Large file

        $promise = File::readStream($path);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });

    it('reads with offset in streaming mode', function () {
        $path = getTestPath('test.txt');
        file_put_contents($path, 'Hello, World!');

        $result = File::readStream($path, ['offset' => 7])->await();

        expect($result)->toBe('World!');
    });

    it('throws exception for non-existent file', function () {
        $path = getTestPath('nonexistent.txt');

        File::readStream($path)->await();
    })->throws(RuntimeException::class);
});

describe('File::readFromGenerator()', function () {
    it('reads file as generator chunks', function () {
        $path = getTestPath('test.txt');
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
        $path = getTestPath('large.txt');
        file_put_contents($path, str_repeat('X', 10 * 1024 * 1024)); // 10MB

        $promise = File::readFromGenerator($path, ['chunk_size' => 1024]);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });

    it('respects offset and length options', function () {
        $path = getTestPath('test.txt');
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
        $path = getTestPath('small.txt');
        $content = 'Small';
        file_put_contents($path, $content);

        $generator = File::readFromGenerator($path, ['chunk_size' => 1024])->await();

        $chunks = iterator_to_array($generator);

        expect(count($chunks))->toBe(1);
        expect($chunks[0])->toBe($content);
    });
});

describe('File::readLines()', function () {
    it('reads file line by line', function () {
        $path = getTestPath('lines.txt');
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
        $path = getTestPath('lines.txt');
        file_put_contents($path, "  Line 1  \n  Line 2  \n");

        $generator = File::readLines($path, ['trim' => true])->await();

        $result = [];
        foreach ($generator as $line) {
            $result[] = $line;
        }

        expect($result)->toBe(['Line 1', 'Line 2']);
    });

    it('skips empty lines when option enabled', function () {
        $path = getTestPath('lines.txt');
        file_put_contents($path, "Line 1\n\nLine 2\n\n\nLine 3\n");

        $generator = File::readLines($path, ['skip_empty' => true])->await();

        $result = [];
        foreach ($generator as $line) {
            $result[] = $line;
        }

        expect($result)->toBe(['Line 1', 'Line 2', 'Line 3']);
    });

    it('can be cancelled during reading', function () {
        $path = getTestPath('many_lines.txt');
        $lines = array_fill(0, 10000, 'Line');
        file_put_contents($path, implode("\n", $lines));

        $promise = File::readLines($path);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();
    });

    it('handles different line endings', function () {
        $path = getTestPath('mixed_endings.txt');
        file_put_contents($path, "Line 1\rLine 2\r\nLine 3\n");

        $generator = File::readLines($path)->await();

        $result = iterator_to_array($generator);

        expect(count($result))->toBeGreaterThanOrEqual(3);
    });
});

describe('File::write()', function () {
    it('writes content to file', function () {
        $path = getTestPath('output.txt');
        $content = 'Test content';

        $bytesWritten = File::write($path, $content)->await();

        expect($bytesWritten)->toBe(strlen($content));
        expect(file_get_contents($path))->toBe($content);
    });

    it('overwrites existing file', function () {
        $path = getTestPath('output.txt');
        file_put_contents($path, 'Old content');

        File::write($path, 'New content')->await();

        expect(file_get_contents($path))->toBe('New content');
    });

    it('creates directories when option enabled', function () {
        $path = getTestPath('subdir/nested/file.txt');

        File::write($path, 'Content', ['create_directories' => true])->await();

        expect(file_exists($path))->toBeTrue();
        expect(file_get_contents($path))->toBe('Content');
    });

    it('writes empty content', function () {
        $path = getTestPath('empty.txt');

        File::write($path, '')->await();

        expect(file_exists($path))->toBeTrue();
        expect(file_get_contents($path))->toBe('');
    });

    it('writes large content', function () {
        $path = getTestPath('large.txt');
        $content = str_repeat('X', 5 * 1024 * 1024); // 5MB

        File::write($path, $content)->await();

        expect(filesize($path))->toBe(strlen($content));
    });

    it('throws exception for invalid path', function () {
        $path = '/invalid/path/that/does/not/exist/file.txt';

        File::write($path, 'content')->await();
    })->throws(RuntimeException::class);
});

describe('File::writeStream()', function () {
    it('writes content using streaming', function () {
        $path = getTestPath('output.txt');
        $content = 'Streamed content';

        $bytesWritten = File::writeStream($path, $content)->await();

        expect($bytesWritten)->toBe(strlen($content));
        expect(file_get_contents($path))->toBe($content);
    });

    it('can be cancelled and removes partial file', function () {
        $path = getTestPath('output.txt');
        $content = str_repeat('X', 10 * 1024 * 1024); // 10MB

        $promise = File::writeStream($path, $content);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();

        usleep(100000);

        expect(file_exists($path))->toBeFalse();
    });

    it('creates directories when option enabled', function () {
        $path = getTestPath('deep/nested/dir/file.txt');

        File::writeStream($path, 'Content', ['create_directories' => true])->await();

        expect(file_exists($path))->toBeTrue();
    });
});

describe('File::writeFromGenerator()', function () {
    it('writes from generator chunks', function () {
        $path = getTestPath('output.txt');

        $generator = function () {
            yield 'First ';
            yield 'Second ';
            yield 'Third';
        };

        File::writeFromGenerator($path, $generator())->await();

        expect(file_get_contents($path))->toBe('First Second Third');
    });

    it('writes with buffer optimization', function () {
        $path = getTestPath('buffered.txt');

        $generator = function () {
            for ($i = 0; $i < 1000; $i++) {
                yield "Line $i\n";
            }
        };

        $bytesWritten = File::writeFromGenerator($path, $generator(), [
            'buffer_size' => 8192,
        ])->await();

        expect($bytesWritten)->toBeGreaterThan(0);
        expect(file_exists($path))->toBeTrue();
    });

    it('can be cancelled and removes partial file', function () {
        $path = getTestPath('output.txt');

        $generator = function () {
            for ($i = 0; $i < 100000; $i++) {
                yield str_repeat('X', 1000);
            }
        };

        $promise = File::writeFromGenerator($path, $generator());
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();

        usleep(100000);

        expect(file_exists($path))->toBeFalse();
    });

    it('handles empty generator', function () {
        $path = getTestPath('empty.txt');

        $generator = function () {
            return;
            yield;
        };

        File::writeFromGenerator($path, $generator())->await();

        expect(file_exists($path))->toBeTrue();
        expect(filesize($path))->toBe(0);
    });

    it('writes single chunk from generator', function () {
        $path = getTestPath('single.txt');

        $generator = function () {
            yield 'Only chunk';
        };

        File::writeFromGenerator($path, $generator())->await();

        expect(file_get_contents($path))->toBe('Only chunk');
    });
});

describe('File::append()', function () {
    it('appends content to existing file', function () {
        $path = getTestPath('append.txt');
        file_put_contents($path, 'First ');

        File::append($path, 'Second')->await();

        expect(file_get_contents($path))->toBe('First Second');
    });

    it('creates file if it does not exist', function () {
        $path = getTestPath('new.txt');

        File::append($path, 'Content')->await();

        expect(file_exists($path))->toBeTrue();
        expect(file_get_contents($path))->toBe('Content');
    });

    it('appends multiple times', function () {
        $path = getTestPath('multi.txt');

        File::append($path, 'A')->await();
        File::append($path, 'B')->await();
        File::append($path, 'C')->await();

        expect(file_get_contents($path))->toBe('ABC');
    });

    it('appends empty string', function () {
        $path = getTestPath('empty_append.txt');
        file_put_contents($path, 'Content');

        File::append($path, '')->await();

        expect(file_get_contents($path))->toBe('Content');
    });
});

// ==================== FILE OPERATIONS ====================

describe('File::exists()', function () {
    it('returns true for existing file', function () {
        $path = getTestPath('exists.txt');
        file_put_contents($path, 'content');

        $exists = File::exists($path)->await();

        expect($exists)->toBeTrue();
    });

    it('returns false for non-existent file', function () {
        $path = getTestPath('nonexistent.txt');

        $exists = File::exists($path)->await();

        expect($exists)->toBeFalse();
    });

    it('returns true for existing directory', function () {
        $path = getTestPath('subdir');
        mkdir($path);

        $exists = File::exists($path)->await();

        expect($exists)->toBeTrue();
    });

    it('returns false for empty path', function () {
        $exists = File::exists('')->await();

        expect($exists)->toBeFalse();
    });
});

describe('File::getStats()', function () {
    it('returns file statistics', function () {
        $path = getTestPath('stats.txt');
        $content = 'Test content';
        file_put_contents($path, $content);

        $stats = File::getStats($path)->await();

        expect($stats)->toBeArray();
        expect($stats['size'])->toBe(strlen($content));
        expect($stats)->toHaveKeys(['size', 'atime', 'mtime', 'ctime']);
    });

    it('throws exception for non-existent file', function () {
        $path = getTestPath('nonexistent.txt');

        File::getStats($path)->await();
    })->throws(RuntimeException::class);

    it('returns stats for directory', function () {
        $path = getTestPath('dir');
        mkdir($path);

        $stats = File::getStats($path)->await();

        expect($stats)->toBeArray();
        expect($stats)->toHaveKey('size');
    });
});

describe('File::delete()', function () {
    it('deletes existing file', function () {
        $path = getTestPath('delete.txt');
        file_put_contents($path, 'content');

        $result = File::delete($path)->await();

        expect($result)->toBeTrue();
        expect(file_exists($path))->toBeFalse();
    });

    it('throws exception for non-existent file', function () {
        $path = getTestPath('nonexistent.txt');

        File::delete($path)->await();
    })->throws(RuntimeException::class);

    it('deletes empty file', function () {
        $path = getTestPath('empty.txt');
        file_put_contents($path, '');

        $result = File::delete($path)->await();

        expect($result)->toBeTrue();
        expect(file_exists($path))->toBeFalse();
    });
});

describe('File::copy()', function () {
    it('copies file successfully', function () {
        $source = getTestPath('source.txt');
        $dest = getTestPath('dest.txt');
        $content = 'Copy me';
        file_put_contents($source, $content);

        $result = File::copy($source, $dest)->await();

        expect($result)->toBeTrue();
        expect(file_get_contents($dest))->toBe($content);
        expect(file_exists($source))->toBeTrue();
    });

    it('overwrites destination if exists', function () {
        $source = getTestPath('source.txt');
        $dest = getTestPath('dest.txt');
        file_put_contents($source, 'New content');
        file_put_contents($dest, 'Old content');

        File::copy($source, $dest)->await();

        expect(file_get_contents($dest))->toBe('New content');
    });

    it('throws exception for non-existent source', function () {
        $source = getTestPath('nonexistent.txt');
        $dest = getTestPath('dest.txt');

        File::copy($source, $dest)->await();
    })->throws(RuntimeException::class);

    it('copies empty file', function () {
        $source = getTestPath('empty.txt');
        $dest = getTestPath('dest.txt');
        file_put_contents($source, '');

        File::copy($source, $dest)->await();

        expect(file_exists($dest))->toBeTrue();
        expect(filesize($dest))->toBe(0);
    });
});

describe('File::copyStream()', function () {
    it('copies file using streaming', function () {
        $source = getTestPath('source.txt');
        $dest = getTestPath('dest.txt');
        $content = str_repeat('Copy', 1000);
        file_put_contents($source, $content);

        $result = File::copyStream($source, $dest)->await();

        expect($result)->toBeTrue();
        expect(file_get_contents($dest))->toBe($content);
    });

    it('can be cancelled and removes partial destination', function () {
        $source = getTestPath('large.txt');
        $dest = getTestPath('dest.txt');
        file_put_contents($source, str_repeat('X', 10 * 1024 * 1024));

        $promise = File::copyStream($source, $dest);
        $promise->cancel();

        expect($promise->isCancelled())->toBeTrue();

        usleep(100000);

        expect(file_exists($dest))->toBeFalse();
    });
});

describe('File::rename()', function () {
    it('renames file successfully', function () {
        $oldPath = getTestPath('old.txt');
        $newPath = getTestPath('new.txt');
        file_put_contents($oldPath, 'content');

        $result = File::rename($oldPath, $newPath)->await();

        expect($result)->toBeTrue();
        expect(file_exists($newPath))->toBeTrue();
        expect(file_exists($oldPath))->toBeFalse();
    });

    it('moves file to different directory', function () {
        $oldPath = getTestPath('file.txt');
        $newDir = getTestPath('subdir');
        mkdir($newDir);
        $newPath = $newDir . '/file.txt';
        file_put_contents($oldPath, 'content');

        File::rename($oldPath, $newPath)->await();

        expect(file_exists($newPath))->toBeTrue();
        expect(file_exists($oldPath))->toBeFalse();
    });

    it('throws exception for non-existent source', function () {
        $oldPath = getTestPath('nonexistent.txt');
        $newPath = getTestPath('new.txt');

        File::rename($oldPath, $newPath)->await();
    })->throws(RuntimeException::class);
});

describe('File::createDirectory()', function () {
    it('creates directory successfully', function () {
        $path = getTestPath('newdir');

        $result = File::createDirectory($path)->await();

        expect($result)->toBeTrue();
        expect(is_dir($path))->toBeTrue();
    });

    it('creates nested directories with recursive option', function () {
        $path = getTestPath('parent/child/grandchild');

        File::createDirectory($path, ['recursive' => true])->await();

        expect(is_dir($path))->toBeTrue();
    });

    it('sets directory permissions', function () {
        $path = getTestPath('perms');

        File::createDirectory($path, ['mode' => 0755])->await();

        expect(is_dir($path))->toBeTrue();
    });

    it('throws exception when directory exists', function () {
        $path = getTestPath('existing');
        mkdir($path);

        File::createDirectory($path)->await();
    })->throws(RuntimeException::class);
});

describe('File::removeDirectory()', function () {
    it('removes empty directory', function () {
        $path = getTestPath('removeme');
        mkdir($path);

        $result = File::removeDirectory($path)->await();

        expect($result)->toBeTrue();
        expect(is_dir($path))->toBeFalse();
    });

    it('removes non-empty directory recursively', function () {
        $path = getTestPath('parent');
        mkdir($path . '/child', 0777, true);
        file_put_contents($path . '/child/file.txt', 'content');

        File::removeDirectory($path)->await();

        expect(is_dir($path))->toBeFalse();
    });

    it('throws exception for non-existent directory', function () {
        $path = getTestPath('nonexistent');

        File::removeDirectory($path)->await();
    })->throws(RuntimeException::class);
});

describe('Edge cases', function () {
    it('handles binary file content', function () {
        $path = getTestPath('binary.dat');
        $binary = pack('C*', 0, 1, 2, 255, 254, 253);
        file_put_contents($path, $binary);

        $result = File::read($path)->await();

        expect($result)->toBe($binary);
    });

    it('handles unicode content', function () {
        $path = getTestPath('unicode.txt');
        $content = '你好世界 🌍 مرحبا العالم';
        file_put_contents($path, $content);

        $result = File::read($path)->await();

        expect($result)->toBe($content);
    });

    it('handles very long file paths', function () {
        $longName = str_repeat('a', 200) . '.txt';
        $path = getTestPath($longName);

        File::write($path, 'content')->await();

        expect(file_exists($path))->toBeTrue();
    });

    it('handles special characters in filename', function () {
        $path = getTestPath('file-with-special_chars@123.txt');

        File::write($path, 'content')->await();

        expect(file_exists($path))->toBeTrue();
    });
});
