<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Exceptions;

/**
 * TransportError describes a failure below the API contract: a network error,
 * a malformed response, or an HTTP status that did not carry a problem body.
 * It never includes the request Authorization header or the Edge API key.
 */
class TransportException extends GoldskyException
{
    public function __construct(
        public readonly string $op,
        public readonly int $statusCode = 0,
        string $message = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            $message !== '' ? "goldsky {$op}: {$message}" : "goldsky {$op}: transport error",
            $statusCode,
            $previous,
        );
    }
}
