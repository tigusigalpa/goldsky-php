<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\GraphQL;

use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Tigusigalpa\Goldsky\Exceptions\ProblemDetails;
use Tigusigalpa\Goldsky\Exceptions\TransportException;
use Tigusigalpa\Goldsky\Requester;

/**
 * GraphQLClient queries Subgraph GraphQL data-plane endpoints.
 *
 * Public endpoints use
 *   https://api.goldsky.com/api/public/{project_id}/subgraphs/{subgraph_name}/{version_or_tag}/gn
 * and have a documented default rate limit of 50 requests per 10 seconds.
 * Private endpoints use https://api.goldsky.com/api/private/.../gn and require
 * the project Bearer token. The SDK does not perform aggressive hidden retries
 * against these endpoints.
 */
final class GraphQLClient
{
    public function __construct(
        private readonly Requester $requester,
        private string $baseURL,
        private string $edgeAPIKey,
    ) {
    }

    public function publicURL(string $projectID, string $subgraphName, string $versionOrTag): string
    {
        return "{$this->baseURL}/public/{$projectID}/subgraphs/{$subgraphName}/{$versionOrTag}/gn";
    }

    public function privateURL(string $projectID, string $subgraphName, string $versionOrTag): string
    {
        return "{$this->baseURL}/private/{$projectID}/subgraphs/{$subgraphName}/{$versionOrTag}/gn";
    }

    public function setEdgeAPIKey(string $key): void
    {
        $this->edgeAPIKey = $key;
    }

    /**
     * Sends a GraphQL request to an arbitrary endpoint URL. When auth is true,
     * the project Bearer token is sent; the token is never logged.
     *
     * @param array{query: string, variables?: array<string, mixed>, operationName?: string} $req
     *
     * @return array{data?: mixed, errors?: array<int, array{message: string}>, extensions?: mixed, status: int}
     */
    public function query(string $endpoint, array $req, bool $auth = false): array
    {
        $options = [
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $this->requester->getConfig()->userAgent,
            ],
            'json' => $req,
        ];
        if ($auth) {
            $options['headers']['Authorization'] = 'Bearer ' . $this->requester->getApiToken();
        }

        try {
            $response = $this->requester->rawRequest('POST', $endpoint, $options, false);
        } catch (GuzzleException $e) {
            throw new TransportException('graphql', 0, $e->getMessage(), $e);
        }

        [$status, $body] = $response;
        $out = ['status' => $status];
        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $out = array_merge($out, $decoded);
            }
        }
        if ($status < 200 || $status >= 300) {
            throw new ProblemDetails(
                type: 'about:blank',
                status: $status,
                detail: 'GraphQL endpoint returned non-2xx status',
                rawBody: $body,
            );
        }
        return $out;
    }

    /**
     * Queries a public Subgraph GraphQL endpoint.
     *
     * @param array{query: string, variables?: array<string, mixed>, operationName?: string} $req
     *
     * @return array{data?: mixed, errors?: array<int, array{message: string}>, extensions?: mixed, status: int}
     */
    public function queryPublic(string $projectID, string $subgraphName, string $versionOrTag, array $req): array
    {
        return $this->query($this->publicURL($projectID, $subgraphName, $versionOrTag), $req, false);
    }

    /**
     * Queries a private Subgraph GraphQL endpoint using the project Bearer token.
     *
     * @param array{query: string, variables?: array<string, mixed>, operationName?: string} $req
     *
     * @return array{data?: mixed, errors?: array<int, array{message: string}>, extensions?: mixed, status: int}
     */
    public function queryPrivate(string $projectID, string $subgraphName, string $versionOrTag, array $req): array
    {
        return $this->query($this->privateURL($projectID, $subgraphName, $versionOrTag), $req, true);
    }

    public function hasErrors(array $response): bool
    {
        return !empty($response['errors']);
    }
}
