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
     * Decodes one JSON object response. Empty, malformed, and non-object
     * bodies are contract violations rather than successful empty responses.
     *
     * @return array<string, mixed>
     */
    protected function decode(string $body): array
    {
        $trimmed = trim($body);
        if ($trimmed === '' || $trimmed[0] !== '{') {
            throw new \Tigusigalpa\Goldsky\Exceptions\TransportException('decode response', 0, 'expected a JSON object');
        }
        $decoded = $this->decodeValue($body);
        if (!is_array($decoded)) {
            throw new \Tigusigalpa\Goldsky\Exceptions\TransportException('decode response', 0, 'expected a JSON object');
        }
        return $decoded;
    }

    /** @return mixed */
    protected function decodeValue(string $body)
    {
        try {
            $trimmed = trim($body);
            if ($trimmed === '') {
                throw new \JsonException('empty response body');
            }
            return json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \Tigusigalpa\Goldsky\Exceptions\TransportException('decode response', 0, $e->getMessage(), $e);
        }
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

    protected function validateSubgraphTarget(string $name, string $version = ''): void
    {
        if (!preg_match('/^[a-zA-Z][\\w-]*$/', $name)) {
            throw new \Tigusigalpa\Goldsky\Exceptions\GoldskyException(
                "invalid subgraph name \"{$name}\": must start with a letter and contain only letters, numbers, underscores, and hyphens"
            );
        }
        if ($version !== '' && !preg_match('/^[a-zA-Z0-9][\\w+.-]*$/', $version)) {
            throw new \Tigusigalpa\Goldsky\Exceptions\GoldskyException("invalid subgraph version or tag \"{$version}\"");
        }
    }

    protected function validateResourceName(string $kind, string $name): void
    {
        if (trim($name) === '') {
            throw new \Tigusigalpa\Goldsky\Exceptions\GoldskyException("goldsky: {$kind} name is required");
        }
    }
}
