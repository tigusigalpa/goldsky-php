<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Exceptions;

/**
 * ProblemDetails is an RFC 9457 (https://www.rfc-editor.org/rfc/rfc9457)
 * application/problem+json failure returned by the Goldsky REST control plane.
 *
 * Callers must branch on getType() (a stable URI) rather than getTitle() or
 * getDetail(), which are human-readable prose and may change. Every type
 * dereferences to a page in the Goldsky error catalogue at
 * https://api.goldsky.com/api/errors.
 */
class ProblemDetails extends GoldskyException
{
    /** @var array<string, string|string[]> */
    private array $headers;

    /** @param array<string, string|string[]> $headers */
    public function __construct(
        public readonly string $type = 'about:blank',
        public readonly string $title = '',
        public readonly int $status = 0,
        public readonly string $detail = '',
        public readonly string $instance = '',
        /** @var array<int, array{field?: string, message: string}> $errors */
        public array $errors = [],
        array $headers = [],
        public readonly string $rawBody = '',
    ) {
        $this->headers = $headers;
        $code = $status !== 0 ? $status : 0;
        $message = $this->buildMessage();
        parent::__construct($message, $code);
    }

    private function buildMessage(): string
    {
        $status = $this->status !== 0 ? $this->status : 200;
        if ($this->detail !== '') {
            return "goldsky API error {$status} ({$this->type}): {$this->detail}";
        }
        if ($this->title !== '') {
            return "goldsky API error {$status} ({$this->type}): {$this->title}";
        }
        return "goldsky API error {$status} ({$this->type})";
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getDetail(): string
    {
        return $this->detail;
    }

    public function getInstance(): string
    {
        return $this->instance;
    }

    /** @return array<int, array{field?: string, message: string}> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string, string|string[]> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    // Classification predicates. These inspect status because the type URI
    // catalogue is large; callers that need exact problem identity should
    // compare getType().

    public function isValidation(): bool
    {
        return $this->status === 400;
    }

    public function isAuthentication(): bool
    {
        return $this->status === 401;
    }

    public function isSubscription(): bool
    {
        return $this->status === 402;
    }

    public function isPermission(): bool
    {
        return $this->status === 403;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }

    public function isConflict(): bool
    {
        return $this->status === 409;
    }

    public function isUnprocessable(): bool
    {
        return $this->status === 422;
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500 && $this->status < 600;
    }

    /**
     * Returns the server-recommended wait from the Retry-After header, or
     * [0, false] if absent or unparseable.
     *
     * @return array{0: int, 1: bool}
     */
    public function retryAfter(): array
    {
        $value = $this->headers['Retry-After'] ?? $this->headers['retry-after'] ?? '';
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }
        return self::parseRetryAfter((string) $value);
    }

    /**
     * @return array{0: int, 1: bool}
     */
    public static function parseRetryAfter(string $value): array
    {
        $value = trim($value);
        if ($value === '') {
            return [0, false];
        }
        if (ctype_digit($value) || (strlen($value) > 0 && $value[0] === '-' && ctype_digit(substr($value, 1)))) {
            $n = (int) $value;
            if ($n < 0) {
                return [0, false];
            }
            return [$n, true];
        }
        $ts = strtotime($value);
        if ($ts !== false) {
            $diff = $ts - time();
            if ($diff < 0) {
                return [0, true];
            }
            return [(int) $diff, true];
        }
        return [0, false];
    }
}
