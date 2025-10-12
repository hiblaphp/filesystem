<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when a file write operation fails.
 */
class FileWriteException extends FileSystemException
{
    public function __construct(
        string $path,
        string $reason = 'Write operation failed',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $reason,
            'write',
            $path,
            0,
            $previous
        );
    }
}
