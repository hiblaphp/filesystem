<?php

namespace Hibla\Filesystem;

use Generator;
use Hibla\Filesystem\Handlers\FileHandler;
use Hibla\Promise\Interfaces\CancellablePromiseInterface;
use Hibla\Promise\Interfaces\PromiseInterface;

/**
 * Static API for asynchronous file and directory operations.
 *
 * This class provides a convenient facade for async file operations with two types of promises:
 * - PromiseInterface: For fast, atomic operations that cannot be cancelled
 * - CancellablePromiseInterface: For streaming operations that support mid-flight cancellation
 */
final class File
{
    /**
     * @var FileHandler|null Cached singleton instance of the async operations handler
     */
    private static ?FileHandler $asyncOps = null;

    /**
     * Get the singleton instance of FileHandler with lazy initialization.
     *
     * @return FileHandler The shared async file operations handler instance
     */
    protected static function getAsyncFileOperations(): FileHandler
    {
        if (self::$asyncOps === null) {
            self::$asyncOps = new FileHandler();
        }

        return self::$asyncOps;
    }

    /**
     * Reset the cached FileHandler instance.
     *
     * Primarily for testing purposes to ensure clean state between test runs.
     */
    public static function reset(): void
    {
        self::$asyncOps = null;
    }

    /**
     * Asynchronously read the entire contents of a file.
     *
     * NON-CANCELLABLE: Operation completes atomically.
     * For large files, consider using readStream() or readFromGenerator() for better memory efficiency.
     *
     * @param  string  $path  The path to the file to read
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'offset' => int: Starting position (default: 0)
     *                                         - 'length' => int|null: Max bytes to read (default: null = all)
     * @return PromiseInterface<string> Promise resolving to complete file contents
     *
     * @throws \RuntimeException If file cannot be read or doesn't exist
     */
    public static function read(string $path, array $options = []): PromiseInterface
    {
        return self::getAsyncFileOperations()->readFile($path, $options);
    }

    /**
     * Asynchronously read a file using streaming (cancellable).
     *
     * CANCELLABLE: Can be cancelled mid-operation.
     * Use when: User control, timeouts, or conditional reading needed.
     * Still loads full file into memory but can be stopped early.
     *
     * @param  string  $path  The path to the file to read
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'offset' => int: Starting position
     *                                         - 'length' => int|null: Max bytes to read
     * @return CancellablePromiseInterface<string> Promise resolving to file contents (cancellable)
     *
     * @throws \RuntimeException If file cannot be opened or read
     */
    public static function readStream(string $path, array $options = []): CancellablePromiseInterface
    {
        return self::getAsyncFileOperations()->readFileStream($path, $options);
    }

    /**
     * Read a file as a generator for memory-efficient streaming.
     *
     * CANCELLABLE: Can be cancelled mid-operation.
     * MEMORY EFFICIENT: Only one chunk in memory at a time.
     *
     * Ideal for processing large files without loading entirely into memory.
     *
     * @param  string  $path  The path to the file
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'chunk_size' => int: Bytes per chunk (default: 8192)
     *                                         - 'offset' => int: Starting position (default: 0)
     *                                         - 'length' => int|null: Total bytes to read (default: null = all)
     * @return CancellablePromiseInterface<Generator<string>> Promise resolving to generator yielding chunks
     *
     * @throws \RuntimeException If file cannot be read
     */
    public static function readFromGenerator(string $path, array $options = []): CancellablePromiseInterface
    {
        return self::getAsyncFileOperations()->readFileFromGenerator($path, $options);
    }

    /**
     * Read a file line-by-line as a generator.
     *
     * CANCELLABLE: Can be cancelled mid-operation.
     * MEMORY EFFICIENT: Only current line in memory.
     *
     * Perfect for processing large text files, logs, or CSV files.
     *
     * @param  string  $path  The path to the file
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'chunk_size' => int: Internal buffer size (default: 8192)
     *                                         - 'trim' => bool: Trim whitespace from lines (default: false)
     *                                         - 'skip_empty' => bool: Skip empty lines (default: false)
     * @return CancellablePromiseInterface<Generator<string>> Promise resolving to generator yielding lines
     *
     * @throws \RuntimeException If file cannot be read
     */
    public static function readLines(string $path, array $options = []): CancellablePromiseInterface
    {
        return self::getAsyncFileOperations()->readFileLines($path, $options);
    }

    /**
     * Asynchronously write data to a file.
     *
     * NON-CANCELLABLE: Operation completes atomically.
     * For large data or when cancellation needed, use writeStream() or writeFromGenerator().
     *
     * @param  string  $path  The path where the file should be written
     * @param  string  $data  The data to write
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'create_directories' => bool: Create parent dirs (default: false)
     *                                         - 'flags' => int: File operation flags
     * @return PromiseInterface<int> Promise resolving to number of bytes written
     *
     * @throws \RuntimeException If file cannot be written
     */
    public static function write(string $path, string $data, array $options = []): PromiseInterface
    {
        return self::getAsyncFileOperations()->writeFile($path, $data, $options);
    }

    /**
     * Asynchronously write data using streaming (cancellable).
     *
     * CANCELLABLE: Can be cancelled mid-operation, partial file will be deleted.
     * Use when: User control, timeouts, or conditional writing needed.
     *
     * @param  string  $path  The path where the file should be written
     * @param  string  $data  The data to write
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'create_directories' => bool: Create parent dirs
     *                                         - 'flags' => int: File operation flags
     * @return CancellablePromiseInterface<int> Promise resolving to bytes written (cancellable)
     *
     * @throws \RuntimeException If file cannot be written
     */
    public static function writeStream(string $path, string $data, array $options = []): CancellablePromiseInterface
    {
        return self::getAsyncFileOperations()->writeFileStream($path, $data, $options);
    }

    /**
     * Write data from a generator for memory-efficient streaming.
     *
     * CANCELLABLE: Can be cancelled mid-operation, partial file will be deleted.
     * MEMORY EFFICIENT: Only one chunk in memory at a time.
     *
     * Perfect for large datasets, transformations, or generating data on-the-fly.
     *
     * PERFORMANCE TIP: Enable auto-buffering for dramatic speedup with small chunks:
     * File::writeFromGenerator($path, $generator, ['buffer_size' => 8192]);
     *
     * @param  string  $path  The path where the file should be written
     * @param  Generator<string>  $dataGenerator  Generator yielding string chunks
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'buffer_size' => int: Auto-buffer size (0=disabled, recommended: 8192)
     *                                         - 'create_directories' => bool: Create parent dirs
     *                                         - 'flags' => int: File operation flags
     * @return CancellablePromiseInterface<int> Promise resolving to bytes written (cancellable)
     *
     * @throws \RuntimeException If file cannot be written
     */
    public static function writeFromGenerator(string $path, Generator $dataGenerator, array $options = []): CancellablePromiseInterface
    {
        return self::getAsyncFileOperations()->writeFileFromGenerator($path, $dataGenerator, $options);
    }

    /**
     * Asynchronously append data to a file.
     *
     * NON-CANCELLABLE: Operation completes atomically.
     * Creates file if it doesn't exist. Useful for logging or incremental writing.
     *
     * @param  string  $path  The path to the file
     * @param  string  $data  The data to append
     * @return PromiseInterface<int> Promise resolving to number of bytes appended
     *
     * @throws \RuntimeException If file cannot be opened for appending
     */
    public static function append(string $path, string $data): PromiseInterface
    {
        return self::getAsyncFileOperations()->appendFile($path, $data);
    }

    /**
     * Asynchronously check if a file or directory exists.
     *
     * NON-CANCELLABLE: Quick check, completes instantly.
     *
     * @param  string  $path  The filesystem path to check
     * @return PromiseInterface<bool> Promise resolving to true if path exists
     *
     * @throws \RuntimeException If check fails due to system errors
     */
    public static function exists(string $path): PromiseInterface
    {
        return self::getAsyncFileOperations()->fileExists($path);
    }

    /**
     * Asynchronously retrieve file statistics and metadata.
     *
     * NON-CANCELLABLE: Quick operation, completes instantly.
     *
     * @param  string  $path  The path to get statistics for
     * @return PromiseInterface<array<string, mixed>> Promise resolving to file stats array
     *
     * @throws \RuntimeException If file doesn't exist or stats cannot be retrieved
     */
    public static function getStats(string $path): PromiseInterface
    {
        return self::getAsyncFileOperations()->getFileStats($path);
    }

    /**
     * Asynchronously delete a file.
     *
     * NON-CANCELLABLE: Operation completes atomically.
     * Use with caution - cannot be undone.
     *
     * @param  string  $path  The path to the file to delete
     * @return PromiseInterface<bool> Promise resolving to true on successful deletion
     *
     * @throws \RuntimeException If file doesn't exist or cannot be deleted
     */
    public static function delete(string $path): PromiseInterface
    {
        return self::getAsyncFileOperations()->deleteFile($path);
    }

    /**
     * Asynchronously copy a file.
     *
     * NON-CANCELLABLE: Operation completes atomically.
     * For large files with cancellation support, use copyStream().
     *
     * @param  string  $source  The source file path
     * @param  string  $destination  The destination file path
     * @return PromiseInterface<bool> Promise resolving to true on successful copy
     *
     * @throws \RuntimeException If source doesn't exist or destination cannot be written
     */
    public static function copy(string $source, string $destination): PromiseInterface
    {
        return self::getAsyncFileOperations()->copyFile($source, $destination);
    }

    /**
     * Asynchronously copy a file using streaming (cancellable).
     *
     * CANCELLABLE: Can be cancelled mid-operation, partial destination will be deleted.
     * Memory efficient for large files.
     *
     * @param  string  $source  The source file path
     * @param  string  $destination  The destination file path
     * @return CancellablePromiseInterface<bool> Promise resolving to true on successful copy (cancellable)
     *
     * @throws \RuntimeException If source doesn't exist or copy fails
     */
    public static function copyStream(string $source, string $destination): CancellablePromiseInterface
    {
        return self::getAsyncFileOperations()->copyFileStream($source, $destination);
    }

    /**
     * Asynchronously rename or move a file.
     *
     * NON-CANCELLABLE: Operation completes atomically.
     * Can rename within directory or move to different location.
     *
     * @param  string  $oldPath  The current file path
     * @param  string  $newPath  The new file path
     * @return PromiseInterface<bool> Promise resolving to true on successful rename/move
     *
     * @throws \RuntimeException If source doesn't exist or destination cannot be written
     */
    public static function rename(string $oldPath, string $newPath): PromiseInterface
    {
        return self::getAsyncFileOperations()->renameFile($oldPath, $newPath);
    }

    /**
     * Asynchronously create a directory.
     *
     * NON-CANCELLABLE: Operation completes atomically.
     *
     * @param  string  $path  The directory path to create
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'mode' => int: Directory permissions (default: 0755)
     *                                         - 'recursive' => bool: Create parent dirs (default: false)
     * @return PromiseInterface<bool> Promise resolving to true on successful creation
     *
     * @throws \RuntimeException If directory already exists or cannot be created
     */
    public static function createDirectory(string $path, array $options = []): PromiseInterface
    {
        return self::getAsyncFileOperations()->createDirectory($path, $options);
    }

    /**
     * Asynchronously remove a directory.
     *
     * NON-CANCELLABLE: Operation completes atomically.
     * Can remove non-empty directories recursively.
     *
     * @param  string  $path  The directory path to remove
     * @return PromiseInterface<bool> Promise resolving to true on successful removal
     *
     * @throws \RuntimeException If directory doesn't exist or cannot be removed
     */
    public static function removeDirectory(string $path): PromiseInterface
    {
        return self::getAsyncFileOperations()->removeDirectory($path);
    }

    /**
     * Start watching a file or directory for changes.
     *
     * Monitors path asynchronously and executes callback when changes occur.
     * Multiple watchers can be active simultaneously.
     *
     * @param  string  $path  The filesystem path to monitor
     * @param  callable  $callback  Function to execute on changes:
     *                              function(string $event, string $path): void
     *                              - $event: 'modified', 'deleted', 'created', etc.
     *                              - $path: The path where change occurred
     * @param  array<string, mixed>  $options  Optional configuration:
     *                                         - 'interval' => int: Polling interval in milliseconds (default: 1000)
     * @return string Unique watcher ID for use with unwatch()
     *
     * @throws \RuntimeException If path doesn't exist or watcher cannot be established
     */
    public static function watch(string $path, callable $callback, array $options = []): string
    {
        return self::getAsyncFileOperations()->watchFile($path, $callback, $options);
    }

    /**
     * Stop watching a file or directory.
     *
     * Removes watcher by its unique ID. Important for preventing memory leaks.
     *
     * @param  string  $watcherId  The watcher ID returned by watch()
     * @return bool True if watcher was removed, false if ID not found
     */
    public static function unwatch(string $watcherId): bool
    {
        return self::getAsyncFileOperations()->unwatchFile($watcherId);
    }
}
