<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Tests;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Builds a Guzzle mock handler and a Client wired to it.
     *
     * @param array<int, array{0: int, 1: string, 2?: array<string, string>}> $responses Each entry is [status, body, headers?].
     * @param array{edge_api_key?: string, retry_max_attempts?: int, retry_mutations?: bool, max_response_body_bytes?: int} $config
     * @return array{0: \Tigusigalpa\Goldsky\Client, 1: \GuzzleHttp\Handler\MockHandler, 2: \ArrayObject<int, array<string, mixed>>}
     */
    protected function mockClient(array $responses, array $config = []): array
    {
        $mock = new \GuzzleHttp\Handler\MockHandler();
        $container = new \ArrayObject();
        $history = \GuzzleHttp\Middleware::history($container);
        $handler = \GuzzleHttp\HandlerStack::create($mock);
        $handler->push($history);
        $httpClient = new \GuzzleHttp\Client(['handler' => $handler, 'http_errors' => false]);

        foreach ($responses as $r) {
            $mock->append(new \GuzzleHttp\Psr7\Response($r[0], $r[2] ?? [], $r[1]));
        }

        $cfg = new \Tigusigalpa\Goldsky\Config();
        if (isset($config['edge_api_key'])) {
            $cfg->withEdgeAPIKey($config['edge_api_key']);
        }
        $cfg->withRetryMaxAttempts($config['retry_max_attempts'] ?? 1);
        if (!empty($config['retry_mutations'])) {
            $cfg->withRetryMutations(true);
        }
        if (isset($config['max_response_body_bytes'])) {
            $cfg->withMaxResponseBodyBytes($config['max_response_body_bytes']);
        }

        $sleeper = static fn (int $ms) => null;
        $client = new \Tigusigalpa\Goldsky\Client('test-token', $cfg, $httpClient, null, $sleeper);

        return [$client, $mock, $container];
    }

    /**
     * @param \ArrayObject<int, array<string, mixed>> $container
     * @return array{method: string, path: string, query: string, headers: array<string, string|string[]>, body: string}
     */
    protected function lastRequest(\ArrayObject $container): array
    {
        $this->assertNotEmpty($container, 'no requests were recorded');
        $transactions = $container->getArrayCopy();
        $req = $transactions[array_key_last($transactions)]['request'];
        if (!$req instanceof \Psr\Http\Message\RequestInterface) {
            throw new \UnexpectedValueException('Recorded transaction does not contain a PSR-7 request');
        }
        $uri = $req->getUri();
        $path = $uri->getPath();
        // Strip the base URL path prefix (e.g. /api/v1) for cleaner assertions.
        $basePath = parse_url(\Tigusigalpa\Goldsky\Config::DEFAULT_BASE_URL, PHP_URL_PATH);
        if ($basePath && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }
        return [
            'method' => $req->getMethod(),
            'path' => $path,
            'query' => $uri->getQuery(),
            'headers' => $req->getHeaders(),
            'body' => (string) $req->getBody(),
        ];
    }

    /**
     * @param array<string, mixed> $request
     */
    protected function assertBearer(array $request): void
    {
        $auth = $request['headers']['Authorization'][0] ?? '';
        $this->assertSame('Bearer test-token', $auth, 'Authorization header mismatch');
    }

    /**
     * Builds a Guzzle mock with inline history container (for edge-case tests).
     *
     * @param array<int, Response> $responses
     * @return array{0: \Tigusigalpa\Goldsky\Client, 1: \ArrayObject<int, array<string, mixed>>}
     */
    protected function mockClientWithResponses(array $responses, array $config = []): array
    {
        $mock = new \GuzzleHttp\Handler\MockHandler($responses);
        $container = new \ArrayObject();
        $history = \GuzzleHttp\Middleware::history($container);
        $handler = \GuzzleHttp\HandlerStack::create($mock);
        $handler->push($history);
        $httpClient = new \GuzzleHttp\Client(['handler' => $handler, 'http_errors' => false]);

        $cfg = new \Tigusigalpa\Goldsky\Config();
        if (isset($config['edge_api_key'])) {
            $cfg->withEdgeAPIKey($config['edge_api_key']);
        }
        $cfg->withRetryMaxAttempts($config['retry_max_attempts'] ?? 1);
        if (!empty($config['retry_mutations'])) {
            $cfg->withRetryMutations(true);
        }
        if (isset($config['max_response_body_bytes'])) {
            $cfg->withMaxResponseBodyBytes($config['max_response_body_bytes']);
        }

        $sleeper = static fn (int $ms) => null;
        $client = new \Tigusigalpa\Goldsky\Client('test-token', $cfg, $httpClient, null, $sleeper);

        return [$client, $container];
    }
}
