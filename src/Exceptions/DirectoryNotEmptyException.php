<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when attempting to remove a non-empty directory.
 */
class DirectoryNotEmptyException extends FileSystemException
{
    public function __construct(
        string $path,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            'Directory is not empty',
            'rmdir',
            $path,
            0,
            $previous
        );
    }
}
