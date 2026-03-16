<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ChatServiceException extends RuntimeException
{
    public function __construct(string $message, int $statusCode = 503, ?Throwable $previous = null)
    {
        parent::__construct($message, $statusCode, $previous);
    }

    public function statusCode(): int
    {
        return $this->getCode() > 0 ? $this->getCode() : 503;
    }
}
