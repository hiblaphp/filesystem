<?php

namespace Hibla\Filesystem\Exceptions;

/**
 * Thrown when a streaming operation fails.
 */
class StreamException extends FileSystemException
{
    public function __construct(
        string $path,
        string $operation,
        string $reason = 'Stream operation failed',
        private readonly ?int $bytesProcessed = null,
        ?\Throwable $previous = null
    ) {
        $message = $reason;
        if ($bytesProcessed !== null) {
            $message .= " (processed {$bytesProcessed} bytes before failure)";
        }

        parent::__construct(
            $message,
            $operation,
            $path,
            0,
            $previous
        );
    }

    public function getBytesProcessed(): ?int
    {
        return $this->bytesProcessed;
    }
}
