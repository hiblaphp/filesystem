<?php

namespace Hibla\Filesystem\Handlers;

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
     * @param callable|null $onChunk Callback to process each chunk: fn(string $chunk): void
     * @param array<string,mixed> $options Additional options for the read operation
     * @return CancellablePromiseInterface<int|string> Cancellable promise that resolves with bytes read or content
     *
     * @throws FileNotFoundException If the file does not exist
     * @throws FilePermissionException If insufficient permissions to read
     * @throws FileReadException If the read operation fails
     */
    public function readFileStream(
        string $path,
        ?callable $onChunk = null,
        array $options = []
    ): CancellablePromiseInterface {
        /** @var CancellablePromise<int|string> $promise */
        $promise = new CancellablePromise();
        $options['use_streaming'] = true;
        $options['on_chunk'] = $onChunk;

        if (!isset($options['chunk_size'])) {
            $options['chunk_size'] = 8192;
        }

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
     * Write data to a file using streaming mode with chunk generation callback or direct string content.
     *
     * CANCELLABLE: Can be cancelled mid-operation, partial file will be deleted.
     * Memory-efficient: Generates chunks on-demand via callback instead of holding all data in memory.
     *
     * @param string $path Path to the file to write
     * @param string|callable $content String content to write, or callback that yields chunks: fn(): ?string (return null when done)
     * @param array<string,mixed> $options Additional options for the write operation
     * @return CancellablePromiseInterface<int> Cancellable promise that resolves with bytes written
     *
     * @throws FilePermissionException If insufficient permissions to write
     * @throws FileWriteException If the write operation fails
     */
    public function writeFileStream(
        string $path,
        string|callable $content,
        array $options = []
    ): CancellablePromiseInterface {
        /** @var CancellablePromise<int> $promise */
        $promise = new CancellablePromise();

        if (is_callable($content)) {
            $options['use_streaming'] = true;
            $options['chunk_generator'] = $content;
            $data = null;
        } else {
            $options['use_streaming'] = true;
            $data = $content;
        }

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

        if ($operation === 'write' || $operation === 'append') {
            if (str_contains($errorLower, 'directory does not exist')) {
                return new FileWriteException($path, $error);
            }

            return new FileWriteException($path, $error);
        }

        if ($operation === 'read') {
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
