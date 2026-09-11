<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\API;

use Tigusigalpa\Goldsky\Page;
use Tigusigalpa\Goldsky\Pager;

/**
 * Edge manages Edge endpoints and their lifecycle.
 *
 * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints
 */
final class Edge extends BaseAPI
{
    /**
     * Lists a single page of Edge endpoints.
     *
     * @param array{product?: string, page_size?: int, page_token?: string} $opts
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/listEdgeEndpoints
     */
    public function list(array $opts = []): Page
    {
        $this->validatePageSize($opts['page_size'] ?? 0);
        $query = [];
        if (!empty($opts['product'])) {
            $query['product'] = $opts['product'];
        }
        if (($opts['page_size'] ?? 0) > 0) {
            $query['page_size'] = $opts['page_size'];
        }
        if (!empty($opts['page_token'])) {
            $query['page_token'] = $opts['page_token'];
        }
        [, $body] = $this->requester->request('GET', ['edge'], $query);
        return Page::fromJSON($body);
    }

    /**
     * Returns a pager over Edge endpoints starting at opts.page_token.
     *
     * @param array{product?: string, page_size?: int, page_token?: string} $opts
     */
    public function newPager(array $opts = []): Pager
    {
        $extra = [];
        if (!empty($opts['product'])) {
            $extra['product'] = $opts['product'];
        }
        return new Pager(
            fn (array $q) => $this->list(array_merge($opts, $q)),
            $opts['page_size'] ?? 0,
            $opts['page_token'] ?? '',
            $extra,
        );
    }

    /**
     * Creates an Edge endpoint and returns the one-time API key.
     *
     * @param array{name: string, product?: string, rate_limit_budget?: string, allowed_domains?: array<int, string>} $req
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/createEdgeEndpoint
     */
    public function create(array $req): array
    {
        [, $body] = $this->requester->request('POST', ['edge'], [], $req);
        return $this->decode($body);
    }

    /**
     * Fetches an Edge endpoint.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/getEdgeEndpoint
     */
    public function get(string $name): array
    {
        [, $body] = $this->requester->request('GET', ['edge', $name]);
        $decoded = $this->decode($body);
        return $decoded['data'] ?? $decoded;
    }

    /**
     * Updates an Edge endpoint. Domain changes are applied before rate-limit
     * changes and the update is not transactional.
     *
     * @param array{rate_limit_budget?: string, allowed_domains?: array<int, string>} $req
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/updateEdgeEndpoint
     */
    public function update(string $name, array $req): array
    {
        [, $body] = $this->requester->request('PATCH', ['edge', $name], [], $req);
        $decoded = $this->decode($body);
        return $decoded['data'] ?? $decoded;
    }

    /**
     * Deletes an Edge endpoint. Returns null on 204.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Endpoints/operation/deleteEdgeEndpoint
     */
    public function delete(string $name): void
    {
        $this->requester->request('DELETE', ['edge', $name]);
    }

    /**
     * Pauses an Edge endpoint.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Lifecycle/operation/pauseEdgeEndpoint
     */
    public function pause(string $name): array
    {
        [, $body] = $this->requester->request('PUT', ['edge', $name, 'pause']);
        $decoded = $this->decode($body);
        return $decoded['data'] ?? $decoded;
    }

    /**
     * Resumes a paused Edge endpoint.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Lifecycle/operation/resumeEdgeEndpoint
     */
    public function resume(string $name): array
    {
        [, $body] = $this->requester->request('PUT', ['edge', $name, 'resume']);
        $decoded = $this->decode($body);
        return $decoded['data'] ?? $decoded;
    }

    /**
     * Reveals the Edge endpoint API key. The key is a separate secret from the
     * REST project token and is never logged.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20API%20Keys/operation/revealEdgeEndpointKey
     */
    public function revealKey(string $name): array
    {
        [, $body] = $this->requester->request('GET', ['edge', $name, 'api-key']);
        return $this->decode($body);
    }

    /**
     * Fetches Edge endpoint metrics.
     *
     * @param array{from?: string, to?: string, bucket_size?: string} $opts
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Edge%20Metrics/operation/getEdgeEndpointMetrics
     */
    public function metrics(string $name, array $opts = []): array
    {
        $query = [];
        if (!empty($opts['from'])) {
            $query['from'] = $opts['from'];
        }
        if (!empty($opts['to'])) {
            $query['to'] = $opts['to'];
        }
        if (!empty($opts['bucket_size'])) {
            $query['bucket_size'] = $opts['bucket_size'];
        }
        [, $body] = $this->requester->request('GET', ['edge', $name, 'metrics'], $query);
        return $this->decode($body);
    }
}
