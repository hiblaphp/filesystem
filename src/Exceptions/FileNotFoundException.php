<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when a file or directory does not exist.
 */
class FileNotFoundException extends FileSystemException
{
    public function __construct(
        string $path,
        string $operation = 'access',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            'File or directory not found',
            $operation,
            $path,
            0,
            $previous
        );
    }
}
