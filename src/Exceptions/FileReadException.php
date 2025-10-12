<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when a file read operation fails.
 */
class FileReadException extends FileSystemException
{
    public function __construct(
        string $path,
        string $reason = 'Read operation failed',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $reason,
            'read',
            $path,
            0,
            $previous
        );
    }
}
