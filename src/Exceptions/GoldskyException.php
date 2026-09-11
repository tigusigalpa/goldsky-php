<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Exceptions;

/**
 * Base exception for all Goldsky SDK errors.
 */
class GoldskyException extends \RuntimeException
{
    public function __construct(string $message, int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
