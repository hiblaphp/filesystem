<?php

namespace Hibla\Filesystem\Handlers;

use Generator;
use Hibla\EventLoop\EventLoop;
use Hibla\Filesystem\Exceptions\FileAlreadyExistsException;
use Hibla\Filesystem\Exceptions\FileCopyException;
use Hibla\Filesystem\Exceptions\FileNotFoundException;
use Hibla\Filesystem\Exceptions\FilePermissionException;
use Hibla\Filesystem\Exceptions\FileReadException;
use Hibla\Filesystem\Exceptions\FileSystemException;
use Hibla\Filesystem\Exceptions\FileWriteException;
use Hibla\Promise\CancellablePromise;
use Hibla\Promise\Interfaces\CancellablePromiseInterface;
use Hibla\Promise\Interfaces\PromiseInterface;
use Hibla\Promise\Promise;

/**
 * Async file operations (non-blocking, with selective cancellation support).
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
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $path Path to the file to read
     * @param array<string,mixed> $options Additional options for the read operation
     * @return PromiseInterface<string> Promise that resolves with file contents
     *
     * @throws FileNotFoundException If the file does not exist
     * @throws FilePermissionException If insufficient permissions to read
     * @throws FileReadException If the read operation fails
     */
    public function readFile(string $path, array $options = []): PromiseInterface
    {
        /** @var Promise<string> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'read',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'read', $path));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        return $promise;
    }

    /**
     * Open a file for streaming reads asynchronously.
     *
     * CANCELLABLE: Can be cancelled mid-operation.
     * Use when: User control, timeouts, or conditional reading needed.
     *
     * @param string $path Path to the file to read
     * @param array<string,mixed> $options Additional options for the read operation
     * @return CancellablePromiseInterface<string> Cancellable promise that resolves with file contents
     *
     * @throws FileNotFoundException If the file does not exist
     * @throws FilePermissionException If insufficient permissions to read
     * @throws FileReadException If the read operation fails
     */
    public function readFileStream(string $path, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<string> $promise */
        $promise = new CancellablePromise();
        $options['use_streaming'] = true;

        $operationId = $this->eventLoop->addFileOperation(
            'read',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject($this->createException($error, 'read', $path));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId): void {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Write data to a file asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $path Path to the file to write
     * @param string $data Data to write to the file
     * @param array<string,mixed> $options Additional options for the write operation
     * @return PromiseInterface<int> Promise that resolves with bytes written
     *
     * @throws FilePermissionException If insufficient permissions to write
     * @throws FileWriteException If the write operation fails
     */
    public function writeFile(string $path, string $data, array $options = []): PromiseInterface
    {
        /** @var Promise<int> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'write',
            $path,
            $data,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'write', $path));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        return $promise;
    }

    /**
     * Write data to a file using streaming mode.
     *
     * CANCELLABLE: Can be cancelled mid-operation, partial file will be deleted.
     * Use when: User control, timeouts, or conditional writing needed.
     *
     * @param string $path Path to the file to write
     * @param string $data Data to write to the file
     * @param array<string,mixed> $options Additional options for the write operation
     * @return CancellablePromiseInterface<int> Cancellable promise that resolves with bytes written
     *
     * @throws FilePermissionException If insufficient permissions to write
     * @throws FileWriteException If the write operation fails
     */
    public function writeFileStream(string $path, string $data, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<int> $promise */
        $promise = new CancellablePromise();
        $options['use_streaming'] = true;

        $operationId = $this->eventLoop->addFileOperation(
            'write',
            $path,
            $data,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject($this->createException($error, 'write', $path));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId, $path): void {
            $this->eventLoop->cancelFileOperation($operationId);
            @$this->deleteFile($path);
        });

        return $promise;
    }

    /**
     * Copy a file using streaming operations asynchronously.
     *
     * CANCELLABLE: Can be cancelled mid-operation, partial destination will be deleted.
     *
     * @param string $source Path to the source file
     * @param string $destination Path to the destination file
     * @return CancellablePromiseInterface<bool> Cancellable promise that resolves with true on success
     *
     * @throws FileNotFoundException If the source file does not exist
     * @throws FilePermissionException If insufficient permissions
     * @throws FileCopyException If the copy operation fails
     */
    public function copyFileStream(string $source, string $destination): CancellablePromiseInterface
    {
        /** @var CancellablePromise<bool> $promise */
        $promise = new CancellablePromise();

        $operationId = $this->eventLoop->addFileOperation(
            'copy',
            $source,
            $destination,
            function (?string $error, mixed $result = null) use ($promise, $source, $destination): void {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject($this->createCopyException($error, $source, $destination));
                } else {
                    $promise->resolve($result);
                }
            },
            ['use_streaming' => true]
        );

        $promise->setCancelHandler(function () use ($operationId, $destination): void {
            $this->eventLoop->cancelFileOperation($operationId);
            @$this->deleteFile($destination);
        });

        return $promise;
    }

    /**
     * Append data to a file asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $path Path to the file to append to
     * @param string $data Data to append to the file
     * @return PromiseInterface<int> Promise that resolves with bytes written
     *
     * @throws FilePermissionException If insufficient permissions to write
     * @throws FileWriteException If the append operation fails
     */
    public function appendFile(string $path, string $data): PromiseInterface
    {
        /** @var Promise<int> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'append',
            $path,
            $data,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'append', $path));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        return $promise;
    }

    /**
     * Delete a file asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $path Path to the file to delete
     * @return PromiseInterface<bool> Promise that resolves with true on success
     *
     * @throws FileNotFoundException If the file does not exist
     * @throws FilePermissionException If insufficient permissions to delete
     * @throws FileSystemException If the delete operation fails
     */
    public function deleteFile(string $path): PromiseInterface
    {
        /** @var Promise<bool> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'delete',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'delete', $path));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        return $promise;
    }

    /**
     * Check existence of a path asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $path Path to check for existence
     * @return PromiseInterface<bool> Promise that resolves with true if exists, false otherwise
     *
     * @throws FileSystemException If the check operation fails
     */
    public function fileExists(string $path): PromiseInterface
    {
        /** @var Promise<bool> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'exists',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'exists', $path));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        return $promise;
    }

    /**
     * Get file stats asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $path Path to the file to stat
     * @return PromiseInterface<array<string,mixed>> Promise that resolves with file statistics
     *
     * @throws FileNotFoundException If the file does not exist
     * @throws FilePermissionException If insufficient permissions to stat
     * @throws FileSystemException If the stat operation fails
     */
    public function getFileStats(string $path): PromiseInterface
    {
        /** @var Promise<array<string,mixed>> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'stat',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'stat', $path));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        return $promise;
    }

    /**
     * Create a directory asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $path Path to the directory to create
     * @param array<string,mixed> $options Additional options (e.g., recursive, permissions)
     * @return PromiseInterface<bool> Promise that resolves with true on success
     *
     * @throws FilePermissionException If insufficient permissions to create
     * @throws FileSystemException If the directory creation fails
     */
    public function createDirectory(string $path, array $options = []): PromiseInterface
    {
        /** @var Promise<bool> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'mkdir',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'mkdir', $path));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        return $promise;
    }

    /**
     * Remove a directory asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $path Path to the directory to remove
     * @return PromiseInterface<bool> Promise that resolves with true on success
     *
     * @throws FileNotFoundException If the directory does not exist
     * @throws FilePermissionException If insufficient permissions to remove
     * @throws FileSystemException If the directory removal fails
     */
    public function removeDirectory(string $path): PromiseInterface
    {
        /** @var Promise<bool> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'rmdir',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'rmdir', $path));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        return $promise;
    }

    /**
     * Copy a file asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $source Path to the source file
     * @param string $destination Path to the destination file
     * @return PromiseInterface<bool> Promise that resolves with true on success
     *
     * @throws FileNotFoundException If the source file does not exist
     * @throws FilePermissionException If insufficient permissions
     * @throws FileCopyException If the copy operation fails
     */
    public function copyFile(string $source, string $destination): PromiseInterface
    {
        /** @var Promise<bool> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'copy',
            $source,
            $destination,
            function (?string $error, mixed $result = null) use ($promise, $source, $destination): void {
                if ($error !== null) {
                    $promise->reject($this->createCopyException($error, $source, $destination));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        return $promise;
    }

    /**
     * Rename or move a file asynchronously.
     *
     * Non-cancellable: Operation completes atomically.
     *
     * @param string $oldPath Current path of the file
     * @param string $newPath New path for the file
     * @return PromiseInterface<bool> Promise that resolves with true on success
     *
     * @throws FileNotFoundException If the source file does not exist
     * @throws FilePermissionException If insufficient permissions
     * @throws FileSystemException If the rename operation fails
     */
    public function renameFile(string $oldPath, string $newPath): PromiseInterface
    {
        /** @var Promise<bool> $promise */
        $promise = new Promise();

        $this->eventLoop->addFileOperation(
            'rename',
            $oldPath,
            $newPath,
            function (?string $error, mixed $result = null) use ($promise, $oldPath): void {
                if ($error !== null) {
                    $promise->reject($this->createException($error, 'rename', $oldPath));
                } else {
                    $promise->resolve($result);
                }
            }
        );

        return $promise;
    }

    /**
     * Watch a file or directory for changes.
     *
     * @param string $path Path to watch
     * @param callable $callback Callback to invoke on changes
     * @param array<string,mixed> $options Additional watcher options
     * @return string Watcher ID that can be used to stop watching
     *
     * @throws FileSystemException If the watch operation fails
     */
    public function watchFile(string $path, callable $callback, array $options = []): string
    {
        return $this->eventLoop->addFileWatcher($path, $callback, $options);
    }

    /**
     * Stop watching by watcher ID.
     *
     * @param string $watcherId The watcher ID returned by watchFile
     * @return bool True if the watcher was removed, false otherwise
     */
    public function unwatchFile(string $watcherId): bool
    {
        return $this->eventLoop->removeFileWatcher($watcherId);
    }

    /**
     * Write data from a generator for memory-efficient streaming.
     *
     * CANCELLABLE: Can be cancelled mid-operation, partial file will be deleted.
     *
     * Ideal for large datasets, transformations, or when generating data on-the-fly.
     * Only one chunk is kept in memory at a time.
     *
     * PERFORMANCE TIP: If your generator yields many small strings (< 1KB each),
     * enable auto-buffering for dramatic speedup (40-50x faster):
     *
     * // Slow (90s for 10M lines)
     * $handler->writeFileFromGenerator($path, $generator);
     *
     * // Fast (2s for 10M lines) - Add buffer_size option
     * $handler->writeFileFromGenerator($path, $generator, ['buffer_size' => 8192]);
     *
     * @param string $path Path to the file to write
     * @param Generator<string> $dataGenerator Generator yielding string chunks
     * @param array<string,mixed> $options [
     *                                        'buffer_size' => int,  // Auto-buffer in bytes (0 = disabled, recommended: 8192)
     *                                        'create_directories' => bool,
     *                                        'flags' => int
     *                                        ]
     * @return CancellablePromiseInterface<int> Cancellable promise that resolves with bytes written
     *
     * @throws FilePermissionException If insufficient permissions to write
     * @throws FileWriteException If the write operation fails
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
        $promise = new CancellablePromise();

        $operationId = $this->eventLoop->addFileOperation(
            'write_generator',
            $path,
            $dataGenerator,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject($this->createException($error, 'write_generator', $path));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId, $path): void {
            $this->eventLoop->cancelFileOperation($operationId);
            @$this->deleteFile($path);
        });

        return $promise;
    }

    /**
     * Read a file as a generator for memory-efficient streaming.
     *
     * CANCELLABLE: Can be cancelled mid-operation to stop reading.
     *
     * Reads the file in chunks, yielding each chunk as it's read. Ideal for
     * processing large files without loading them entirely into memory.
     *
     * @param string $path Path to the file to read
     * @param array<string,mixed> $options [
     *                                        'chunk_size' => int,  // Bytes per chunk (default: 8192)
     *                                        'offset' => int,      // Starting position (default: 0)
     *                                        'length' => int|null, // Total bytes to read (default: null = all)
     *                                        ]
     * @return CancellablePromiseInterface<Generator<string>> Cancellable promise that resolves with a generator
     *
     * @throws FileNotFoundException If the file does not exist
     * @throws FilePermissionException If insufficient permissions to read
     * @throws FileReadException If the read operation fails
     */
    public function readFileFromGenerator(string $path, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<Generator<string>> $promise */
        $promise = new CancellablePromise();

        $operationId = $this->eventLoop->addFileOperation(
            'read_generator',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject($this->createException($error, 'read_generator', $path));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId): void {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Read a file as a generator with line-by-line iteration.
     *
     * CANCELLABLE: Can be cancelled mid-operation to stop reading.
     *
     * Reads the file line by line, yielding each line. Memory-efficient for
     * processing large text files.
     *
     * @param string $path Path to the file to read
     * @param array<string,mixed> $options [
     *                                        'chunk_size' => int,     // Internal read buffer (default: 8192)
     *                                        'trim' => bool,          // Trim whitespace from lines (default: false)
     *                                        'skip_empty' => bool,    // Skip empty lines (default: false)
     *                                        ]
     * @return CancellablePromiseInterface<Generator<string>> Cancellable promise that resolves with a line generator
     *
     * @throws FileNotFoundException If the file does not exist
     * @throws FilePermissionException If insufficient permissions to read
     * @throws FileReadException If the read operation fails
     */
    public function readFileLines(string $path, array $options = []): CancellablePromiseInterface
    {
        /** @var CancellablePromise<Generator<string>> $promise */
        $promise = new CancellablePromise();

        $options['read_mode'] = 'lines';

        $operationId = $this->eventLoop->addFileOperation(
            'read_generator',
            $path,
            null,
            function (?string $error, mixed $result = null) use ($promise, $path): void {
                if ($promise->isCancelled()) {
                    return;
                }

                if ($error !== null) {
                    $promise->reject($this->createException($error, 'read_generator', $path));
                } else {
                    $promise->resolve($result);
                }
            },
            $options
        );

        $promise->setCancelHandler(function () use ($operationId): void {
            $this->eventLoop->cancelFileOperation($operationId);
        });

        return $promise;
    }

    /**
     * Create a buffered generator wrapper that batches small yields into larger chunks.
     *
     * This improves performance when the source generator yields many small strings.
     *
     * @param Generator<string> $generator Source generator
     * @param int $bufferSize Target buffer size in bytes (default: 8192)
     * @return Generator<string> Batched generator
     */
    private static function bufferGenerator(Generator $generator, int $bufferSize = 8192): Generator
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

    /**
     * Create appropriate exception based on error message.
     *
     * @param string $error Error message from the operation
     * @param string $operation Operation type (read, write, etc.)
     * @param string $path File path involved in the operation
     * @return \Throwable Appropriate exception instance
     */
    private function createException(string $error, string $operation, string $path): \Throwable
    {
        $errorLower = strtolower($error);

        if (
            str_contains($errorLower, 'already exists') ||
            str_contains($errorLower, 'file exists')
        ) {
            return new FileAlreadyExistsException($path, $operation);
        }

        if (
            str_contains($errorLower, 'permission') ||
            str_contains($errorLower, 'denied') ||
            str_contains($errorLower, 'forbidden')
        ) {
            return new FilePermissionException($path, $operation);
        }

        if ($operation === 'write' || $operation === 'write_generator' || $operation === 'append') {
            if (str_contains($errorLower, 'directory does not exist')) {
                return new FileWriteException($path, $error);
            }

            return new FileWriteException($path, $error);
        }

        if ($operation === 'read' || $operation === 'read_generator') {
            if (
                str_contains($errorLower, 'not found') ||
                str_contains($errorLower, 'no such file') ||
                str_contains($errorLower, 'does not exist')
            ) {
                return new FileNotFoundException($path, $operation);
            }

            return new FileReadException($path, $error);
        }

        if (
            str_contains($errorLower, 'not found') ||
            str_contains($errorLower, 'no such file') ||
            str_contains($errorLower, 'does not exist')
        ) {
            return new FileNotFoundException($path, $operation);
        }

        return new FileSystemException($error, $operation, $path);
    }

    /**
     * Create appropriate exception for copy operations.
     *
     * @param string $error Error message from the operation
     * @param string $source Source file path
     * @param string $destination Destination file path
     * @return \Throwable Appropriate exception instance
     */
    private function createCopyException(string $error, string $source, string $destination): \Throwable
    {
        $errorLower = strtolower($error);

        if (
            str_contains($errorLower, 'not found') ||
            str_contains($errorLower, 'no such file')
        ) {
            return new FileNotFoundException($source, 'copy');
        }

        if (
            str_contains($errorLower, 'permission') ||
            str_contains($errorLower, 'denied')
        ) {
            return new FilePermissionException($source, 'copy');
        }

        return new FileCopyException($source, $destination, $error);
    }
}
