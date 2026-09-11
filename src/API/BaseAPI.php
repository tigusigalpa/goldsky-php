<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\API;

use Tigusigalpa\Goldsky\Requester;

/**
 * BaseAPI is the common base for REST service classes.
 */
abstract class BaseAPI
{
    public function __construct(protected Requester $requester)
    {
    }

    /**
     * Decodes a JSON body to an array, treating an empty body as [].
     *
     * @return array<string, mixed>
     */
    protected function decode(string $body): array
    {
        if ($body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Validates a page_size value (1-200). Zero means use the server default.
     *
     * @throws \Tigusigalpa\Goldsky\Exceptions\GoldskyException
     */
    protected function validatePageSize(int $n): void
    {
        if ($n !== 0 && ($n < 1 || $n > 200)) {
            throw new \Tigusigalpa\Goldsky\Exceptions\GoldskyException(
                "page_size must be between 1 and 200, got {$n}"
            );
        }
    }

    /**
     * Validates a pipeline name against ^[a-z0-9-]{1,50}$.
     */
    protected function validatePipelineName(string $name): void
    {
        if (!preg_match('/^[a-z0-9-]{1,50}$/', $name)) {
            throw new \Tigusigalpa\Goldsky\Exceptions\GoldskyException(
                "invalid pipeline name \"{$name}\": must match ^[a-z0-9-]{1,50}\$"
            );
        }
    }
}
