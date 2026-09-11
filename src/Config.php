<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky;

/**
 * Configuration for a Goldsky client. Built by Client::fromConfig() or via
 * the Config builder methods. All values are resolved at construction time.
 */
final class Config
{
    public const DEFAULT_BASE_URL = 'https://api.goldsky.com/api/v1';
    public const DEFAULT_USER_AGENT = 'goldsky-php/1.0.0';
    public const DEFAULT_EDGE_BASE_URL = 'https://edge.goldsky.com/standard/evm';
    public const DEFAULT_GRAPHQL_BASE_URL = 'https://api.goldsky.com/api';
    public const DEFAULT_MAX_RESPONSE_BODY_BYTES = 16 * 1024 * 1024;

    public string $baseURL = self::DEFAULT_BASE_URL;
    public string $userAgent = self::DEFAULT_USER_AGENT;
    public string $edgeBaseURL = self::DEFAULT_EDGE_BASE_URL;
    public string $graphQLBaseURL = self::DEFAULT_GRAPHQL_BASE_URL;
    public string $edgeAPIKey = '';
    public int $retryMaxAttempts = 3;
    public int $retryInitialBackoffMs = 500;
    public int $retryMaxBackoffMs = 30000;
    public bool $retryMutations = false;
    public float $timeoutSec = 60.0;
    public bool $verifyTls = true;
    public int $maxResponseBodyBytes = self::DEFAULT_MAX_RESPONSE_BODY_BYTES;

    public function withBaseURL(string $url): self
    {
        $this->baseURL = $url;
        return $this;
    }

    public function withUserAgent(string $ua): self
    {
        $this->userAgent = $ua;
        return $this;
    }

    public function withEdgeAPIKey(string $key): self
    {
        $this->edgeAPIKey = $key;
        return $this;
    }

    public function withEdgeBaseURL(string $url): self
    {
        $this->edgeBaseURL = $url;
        return $this;
    }

    public function withGraphQLBaseURL(string $url): self
    {
        $this->graphQLBaseURL = $url;
        return $this;
    }

    public function withRetryMaxAttempts(int $n): self
    {
        $this->retryMaxAttempts = $n;
        return $this;
    }

    public function withRetryMutations(bool $v = true): self
    {
        $this->retryMutations = $v;
        return $this;
    }

    public function withTimeout(float $seconds): self
    {
        $this->timeoutSec = $seconds;
        return $this;
    }

    public function withVerifyTls(bool $v): self
    {
        $this->verifyTls = $v;
        return $this;
    }

    /**
     * Limits the amount of REST, GraphQL, and JSON-RPC response data buffered
     * in memory. The default is 16 MiB.
     */
    public function withMaxResponseBodyBytes(int $bytes): self
    {
        $this->maxResponseBodyBytes = $bytes;
        return $this;
    }
}
