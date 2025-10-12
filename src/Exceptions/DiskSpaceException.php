<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when there is insufficient disk space.
 */
class DiskSpaceException extends FileSystemException
{
    public function __construct(
        string $path,
        string $operation,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            'Insufficient disk space',
            $operation,
            $path,
            0,
            $previous
        );
    }
}
