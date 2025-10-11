<?php

namespace Hibla\Filesystem\Handlers;

use Generator;
use Hibla\EventLoop\EventLoop;
use Hibla\Promise\CancellablePromise;
use Hibla\Promise\Interfaces\CancellablePromiseInterface;

/**
 * Async file operations (non-blocking, cancellable).
 */
final readonly class FileHandler
{
    private EventLoop $eventLoop;

    /**
     * Constructor — attach to the global event loop.
     */
    public function __construct()
    {
        $this->eventLoop = EventLoop::getInstance();
    }

    /**
     * Read a file into memory asynchronously.
     *
     * @param  array<string,mixed>  $options
     * @return CancellablePromiseInterface<string>
     *
     * @throws \RuntimeException
     */
    public function readFile(string $path, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<string> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'read',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Open a file for streaming reads asynchronously.
     *
     * @param  array<string,mixed>  $options
     * @return CancellablePromiseInterface<resource>
     *
     * @throws \RuntimeException
     */
    public function readFileStream(string $path, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<resource> $promise */
        $promise = new CancellablePromise;
        $options['use_streaming'] = true;

        $operationId = $this->eventLoop->addFileOperation(
            'read',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Write data to a file using streaming mode (delegates to writeFile).
     *
     * @param  array<string,mixed>  $options
     * @return CancellablePromiseInterface<int>
     *
     * @throws \RuntimeException
     */
    public function writeFileStream(string $path, string $data, array $options = []): CancellablePromiseInterface
    {
        $options['use_streaming'] = true;

        return $this->writeFile($path, $data, $options);
    }

    /**
     * Copy a file using streaming operations asynchronously.
     *
     * @return CancellablePromiseInterface<bool>
     *
     * @throws \RuntimeException
     */
    public function copyFileStream(string $source, string $destination): CancellablePromiseInterface
    {
        /** @var CancellablePromise<bool> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'copy',
            $source,
            $destination,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            },
            ['use_streaming' => true]
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Write data to a file asynchronously.
     *
     * @param  array<string,mixed>  $options
     * @return CancellablePromiseInterface<int>
     *
     * @throws \RuntimeException
     */
    public function writeFile(string $path, string $data, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<int> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'write',
            $path,
            $data,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Append data to a file asynchronously.
     *
     * @return CancellablePromiseInterface<int>
     *
     * @throws \RuntimeException
     */
    public function appendFile(string $path, string $data): CancellablePromiseInterface
    {
        /** @var CancellablePromise<int> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'append',
            $path,
            $data,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Delete a file asynchronously.
     *
     * @return CancellablePromiseInterface<bool>
     *
     * @throws \RuntimeException
     */
    public function deleteFile(string $path): CancellablePromiseInterface
    {
        /** @var CancellablePromise<bool> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'delete',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Check existence of a path asynchronously.
     *
     * @return CancellablePromiseInterface<bool>
     *
     * @throws \RuntimeException
     */
    public function fileExists(string $path): CancellablePromiseInterface
    {
        /** @var CancellablePromise<bool> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'exists',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Get file stats asynchronously.
     *
     * @return CancellablePromiseInterface<array<string,mixed>>
     *
     * @throws \RuntimeException
     */
    public function getFileStats(string $path): CancellablePromiseInterface
    {
        /** @var CancellablePromise<array<string,mixed>> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'stat',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Create a directory asynchronously.
     *
     * @param  array<string,mixed>  $options
     * @return CancellablePromiseInterface<bool>
     *
     * @throws \RuntimeException
     */
    public function createDirectory(string $path, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<bool> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'mkdir',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Remove an empty directory asynchronously.
     *
     * @return CancellablePromiseInterface<bool>
     *
     * @throws \RuntimeException
     */
    public function removeDirectory(string $path): CancellablePromiseInterface
    {
        /** @var CancellablePromise<bool> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'rmdir',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Copy a file asynchronously.
     *
     * @return CancellablePromiseInterface<bool>
     *
     * @throws \RuntimeException
     */
    public function copyFile(string $source, string $destination): CancellablePromiseInterface
    {
        /** @var CancellablePromise<bool> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'copy',
            $source,
            $destination,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Rename or move a file asynchronously.
     *
     * @return CancellablePromiseInterface<bool>
     *
     * @throws \RuntimeException
     */
    public function renameFile(string $oldPath, string $newPath): CancellablePromiseInterface
    {
        /** @var CancellablePromise<bool> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'rename',
            $oldPath,
            $newPath,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Watch a file or directory for changes.
     *
     * @param  array<string,mixed>  $options
     * @return string Watcher ID
     *
     * @throws \RuntimeException
     */
    public function watchFile(string $path, callable $callback, array $options = []): string
    {
        return $this->eventLoop->addFileWatcher($path, $callback, $options);
    }

    /**
     * Stop watching by watcher ID.
     */
    public function unwatchFile(string $watcherId): bool
    {
        return $this->eventLoop->removeFileWatcher($watcherId);
    }

    /**
     * Write data from a generator for memory-efficient streaming.
     * 
     * Ideal for large datasets, transformations, or when generating data on-the-fly.
     * Only one chunk is kept in memory at a time.
     * 
     * PERFORMANCE TIP: If your generator yields many small strings (< 1KB each),
     * enable auto-buffering for dramatic speedup (40-50x faster):
     * 
     *
     * // Slow (90s for 10M lines)
     * $handler->writeFileFromGenerator($path, $generator);
     * 
     * // Fast (2s for 10M lines) - Add buffer_size option
     * $handler->writeFileFromGenerator($path, $generator, ['buffer_size' => 8192]);
     *
     * @param  Generator<string>  $dataGenerator  Generator yielding string chunks
     * @param  array<string,mixed>  $options  [
     *     'buffer_size' => int,  // Auto-buffer in bytes (0 = disabled, recommended: 8192)
     *     'create_directories' => bool,
     *     'flags' => int
     * ]
     * @return CancellablePromiseInterface<int>
     */
    public function writeFileFromGenerator(
        string $path,
        Generator $dataGenerator,
        array $options = []
    ): CancellablePromiseInterface {
        $bufferSize = $options['buffer_size'] ?? 0;
        if (is_numeric($bufferSize) && (int) $bufferSize > 0) {
            $dataGenerator = self::bufferGenerator($dataGenerator, (int) $bufferSize);
            unset($options['buffer_size']);
        }

        /** @var CancellablePromise<int> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'write_generator',
            $path,
            $dataGenerator,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Read a file as a generator for memory-efficient streaming.
     * 
     * Reads the file in chunks, yielding each chunk as it's read. Ideal for
     * processing large files without loading them entirely into memory.
     * 
     * @param  array<string,mixed>  $options  [
     *     'chunk_size' => int,  // Bytes per chunk (default: 8192)
     *     'offset' => int,      // Starting position (default: 0)
     *     'length' => int|null, // Total bytes to read (default: null = all)
     * ]
     * @return CancellablePromiseInterface<Generator<string>>
     *
     * @throws \RuntimeException
     */
    public function readFileFromGenerator(string $path, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<Generator<string>> $promise */
        $promise = new CancellablePromise;

        $operationId = $this->eventLoop->addFileOperation(
            'read_generator',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Read a file as a generator with line-by-line iteration.
     * 
     * Reads the file line by line, yielding each line. Memory-efficient for
     * processing large text files.
     * 
     * @param  array<string,mixed>  $options  [
     *     'chunk_size' => int,     // Internal read buffer (default: 8192)
     *     'trim' => bool,          // Trim whitespace from lines (default: false)
     *     'skip_empty' => bool,    // Skip empty lines (default: false)
     * ]
     * @return CancellablePromiseInterface<Generator<string>>
     *
     * @throws \RuntimeException
     */
    public function readFileLines(string $path, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<Generator<string>> $promise */
        $promise = new CancellablePromise;

        $options['read_mode'] = 'lines';

        $operationId = $this->eventLoop->addFileOperation(
            'read_generator',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise) {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject(new \RuntimeException($error));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId) {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Create a buffered generator wrapper that batches small yields into larger chunks.
     * 
     * This improves performance when the source generator yields many small strings.
     *
     * @param  Generator<string>  $generator  Source generator
     * @param  int  $bufferSize  Target buffer size in bytes (default: 8192)
     * @return Generator<string>  Batched generator
     */
    public static function bufferGenerator(Generator $generator, int $bufferSize = 8192): Generator
    {
        $buffer = '';

        foreach ($generator as $chunk) {
            $buffer .= $chunk;

            // Yield when buffer reaches target size
            if (strlen($buffer) >= $bufferSize) {
                yield $buffer;
                $buffer = '';
            }
        }

        // Yield any remaining data
        if ($buffer !== '') {
            yield $buffer;
        }
    }
}
