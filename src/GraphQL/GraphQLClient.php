<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\GraphQL;

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
        ?string $unusedEdgeAPIKey = null,
    ) {
    }

    public function publicURL(string $projectID, string $subgraphName, string $versionOrTag): string
    {
        return $this->endpointURL('public', $projectID, $subgraphName, $versionOrTag);
    }

    public function privateURL(string $projectID, string $subgraphName, string $versionOrTag): string
    {
        return $this->endpointURL('private', $projectID, $subgraphName, $versionOrTag);
    }

    /**
     * @deprecated Private GraphQL calls authenticate with the REST project
     * API token. Edge endpoint API keys do not apply to GraphQL.
     */
    public function setEdgeAPIKey(string $key): void
    {
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
        if (!isset($req['query']) || !is_string($req['query']) || trim($req['query']) === '') {
            throw new TransportException('graphql', 0, 'query is required');
        }
        if ($auth && !$this->requester->hasApiToken()) {
            throw new TransportException('graphql', 0, 'REST project API token is required');
        }
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

        [$status, $body] = $this->requester->rawRequest('POST', $endpoint, $options, false);
        $out = ['status' => $status];
        if ($status < 200 || $status >= 300) {
            throw new ProblemDetails(
                type: 'about:blank',
                status: $status,
                detail: 'GraphQL endpoint returned non-2xx status',
                rawBody: $body,
            );
        }

        try {
            $trimmed = trim($body);
            if ($trimmed === '' || $trimmed[0] !== '{') {
                throw new \JsonException('expected a JSON object');
            }
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransportException('graphql', $status, 'decode GraphQL response: ' . $e->getMessage(), $e);
        }
        if (!is_array($decoded)) {
            throw new TransportException('graphql', $status, 'decode GraphQL response: expected a JSON object');
        }
        $out = array_merge($out, $decoded);
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
        $this->validateTarget($projectID, $subgraphName, $versionOrTag);
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
        $this->validateTarget($projectID, $subgraphName, $versionOrTag);
        return $this->query($this->privateURL($projectID, $subgraphName, $versionOrTag), $req, true);
    }

    public function hasErrors(array $response): bool
    {
        return !empty($response['errors']);
    }

    private function endpointURL(string $scope, string $projectID, string $subgraphName, string $versionOrTag): string
    {
        return rtrim($this->baseURL, '/') . '/' . $scope . '/'
            . rawurlencode($projectID) . '/subgraphs/'
            . rawurlencode($subgraphName) . '/' . rawurlencode($versionOrTag) . '/gn';
    }

    private function validateTarget(string $projectID, string $subgraphName, string $versionOrTag): void
    {
        foreach ([
            'project ID' => $projectID,
            'subgraph name' => $subgraphName,
            'version or tag' => $versionOrTag,
        ] as $label => $value) {
            if (trim($value) === '') {
                throw new TransportException('graphql', 0, "{$label} is required");
            }
        }
    }
}
