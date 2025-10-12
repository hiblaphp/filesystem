<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when there are insufficient permissions for the operation.
 */
class FilePermissionException extends FileSystemException
{
    public function __construct(
        string $path,
        string $operation,
        string $requiredPermission = 'read/write',
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "Permission denied (requires {$requiredPermission} access)",
            $operation,
            $path,
            0,
            $previous
        );
    }
}
