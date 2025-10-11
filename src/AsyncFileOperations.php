<?php

namespace Hibla\Filesystem;

use Hibla\Filesystem\Handlers\FileHandler;
use Hibla\Promise\Interfaces\CancellablePromiseInterface;

class AsyncFileOperations
{
    /**
     * @var FileHandler Handles asynchronous file operations
     */
    private FileHandler $fileHandler;

    public function __construct()
    {
        $this->fileHandler = new FileHandler;
    }

    /**
     * Read a file asynchronously.
     *
     * @param  string  $path  The file path to read
     * @param  array<string, mixed>  $options  Options for reading the file
     * @return CancellablePromiseInterface<string> Promise that resolves with file contents
     */
    public function read(string $path, array $options = []): CancellablePromiseInterface
    {
        return $this->fileHandler->readFile($path, $options);
    }

    /**
     * Write to a file asynchronously.
     *
     * @param  string  $path  The file path to write to
     * @param  string  $data  The data to write
     * @param  array<string, mixed>  $options  Options for writing the file
     * @return CancellablePromiseInterface<int> Promise that resolves with bytes written
     */
    public function write(string $path, string $data, array $options = []): CancellablePromiseInterface
    {
        return $this->fileHandler->writeFile($path, $data, $options);
    }

    /**
     * Append to a file asynchronously.
     *
     * @param  string  $path  The file path to append to
     * @param  string  $data  The data to append
     * @return CancellablePromiseInterface<int> Promise that resolves with bytes written
     */
    public function append(string $path, string $data): CancellablePromiseInterface
    {
        return $this->fileHandler->appendFile($path, $data);
    }

    /**
     * Check if file exists asynchronously.
     *
     * @param  string  $path  The file path to check
     * @return CancellablePromiseInterface<bool> Promise that resolves with existence status
     */
    public function exists(string $path): CancellablePromiseInterface
    {
        return $this->fileHandler->fileExists($path);
    }

    /**
     * Get file information asynchronously.
     *
     * @param  string  $path  The file path to get stats for
     * @return CancellablePromiseInterface<array<string, mixed>> Promise that resolves with file stats
     */
    public function getStats(string $path): CancellablePromiseInterface
    {
        return $this->fileHandler->getFileStats($path);
    }

    /**
     * Delete a file asynchronously.
     *
     * @param  string  $path  The file path to delete
     * @return CancellablePromiseInterface<bool> Promise that resolves with success status
     */
    public function delete(string $path): CancellablePromiseInterface
    {
        return $this->fileHandler->deleteFile($path);
    }

    /**
     * Create a directory asynchronously.
     *
     * @param  string  $path  The directory path to create
     * @param  array<string, mixed>  $options  Options for directory creation
     * @return CancellablePromiseInterface<bool> Promise that resolves with success status
     */
    public function createDirectory(string $path, array $options = []): CancellablePromiseInterface
    {
        return $this->fileHandler->createDirectory($path, $options);
    }

    /**
     * Remove a directory asynchronously.
     *
     * @param  string  $path  The directory path to remove
     * @return CancellablePromiseInterface<bool> Promise that resolves with success status
     */
    public function removeDirectory(string $path): CancellablePromiseInterface
    {
        return $this->fileHandler->removeDirectory($path);
    }

    /**
     * Copy a file asynchronously.
     *
     * @param  string  $source  The source file path
     * @param  string  $destination  The destination file path
     * @return CancellablePromiseInterface<bool> Promise that resolves with success status
     */
    public function copy(string $source, string $destination): CancellablePromiseInterface
    {
        return $this->fileHandler->copyFile($source, $destination);
    }

    /**
     * Rename a file asynchronously.
     *
     * @param  string  $oldPath  The current file path
     * @param  string  $newPath  The new file path
     * @return CancellablePromiseInterface<bool> Promise that resolves with success status
     */
    public function rename(string $oldPath, string $newPath): CancellablePromiseInterface
    {
        return $this->fileHandler->renameFile($oldPath, $newPath);
    }

    /**
     * Watch a file for changes asynchronously.
     *
     * @param  string  $path  The file path to watch
     * @param  callable  $callback  The callback to execute on changes
     * @param  array<string, mixed>  $options  Options for watching
     * @return string The watcher ID
     */
    public function watch(string $path, callable $callback, array $options = []): string
    {
        return $this->fileHandler->watchFile($path, $callback, $options);
    }

    /**
     * Unwatch a file for changes asynchronously.
     *
     * @param  string  $watcherId  The watcher ID to remove
     * @return bool Success status
     */
    public function unwatch(string $watcherId): bool
    {
        return $this->fileHandler->unwatchFile($watcherId);
    }

    /**
     * Read a file as a stream asynchronously.
     *
     * @param  string  $path  The file path to read
     * @param  array<string, mixed>  $options  Options for reading the file stream
     * @return CancellablePromiseInterface<resource> Promise that resolves with file stream
     */
    public function readStream(string $path, array $options = []): CancellablePromiseInterface
    {
        return $this->fileHandler->readFileStream($path, $options);
    }

    /**
     * Write to a file as a stream asynchronously.
     *
     * @param  string  $path  The file path to write to
     * @param  string  $data  The data to write
     * @param  array<string, mixed>  $options  Options for writing the file stream
     * @return CancellablePromiseInterface<int> Promise that resolves with bytes written
     */
    public function writeStream(string $path, string $data, array $options = []): CancellablePromiseInterface
    {
        return $this->fileHandler->writeFileStream($path, $data, $options);
    }

    /**
     * Copy a file using streams asynchronously.
     *
     * @param  string  $source  The source file path
     * @param  string  $destination  The destination file path
     * @return CancellablePromiseInterface<bool> Promise that resolves with success status
     */
    public function copyStream(string $source, string $destination): CancellablePromiseInterface
    {
        return $this->fileHandler->copyFileStream($source, $destination);
    }
}
