<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Base exception for all filesystem operations.
 */
class FileSystemException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $operation,
        private readonly string $path,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $formattedMessage = sprintf(
            "File operation '%s' failed on '%s': %s",
            $operation,
            $path,
            $message
        );

        parent::__construct($formattedMessage, $code, $previous);
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
