<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when a file copy operation fails.
 */
class FileCopyException extends FileSystemException
{
    public function __construct(
        string $sourcePath,
        private readonly string $destinationPath,
        string $reason = 'Copy operation failed',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $reason . " (destination: {$destinationPath})",
            'copy',
            $sourcePath,
            0,
            $previous
        );
    }

    public function getSourcePath(): string
    {
        return $this->getPath();
    }

    public function getDestinationPath(): string
    {
        return $this->destinationPath;
    }
}
