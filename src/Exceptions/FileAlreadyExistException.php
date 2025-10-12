<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when attempting to create a file/directory that already exists.
 */
class FileAlreadyExistsException extends FileSystemException
{
    public function __construct(
        string $path,
        string $operation = 'create',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            'File or directory already exists',
            $operation,
            $path,
            0,
            $previous
        );
    }
}
