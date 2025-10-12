<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when a path is invalid or malformed.
 */
class InvalidPathException extends FileSystemException
{
    public function __construct(
        string $path,
        string $operation,
        string $reason = 'Invalid path',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            $reason,
            $operation,
            $path,
            0,
            $previous
        );
    }
}
