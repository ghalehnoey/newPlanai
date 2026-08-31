<?php

namespace App\Exceptions;

use RuntimeException;

class AiRequestException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpCode = 0,
        public readonly int $curlErrno = 0,
        public readonly int $elapsedMs = 0,
        public readonly string $primaryIp = '',
        public readonly ?int $retryAfterSeconds = null,
    ) {
        parent::__construct($message);
    }
}
