<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\API;

use Tigusigalpa\Goldsky\Exceptions\GoldskyException;

/**
 * Webhooks manages Subgraph entity webhooks.
 *
 * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks
 */
final class Webhooks extends BaseAPI
{
    /**
     * Lists project webhooks.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks/operation/listWebhooks
     */
    public function list(): array
    {
        [, $body] = $this->requester->request('GET', ['subgraphs', 'webhooks']);
        return $this->decode($body);
    }

    /**
     * Creates an entity webhook and returns the one-time delivery secret.
     *
     * The secret is sent as the goldsky-webhook-secret header on every
     * delivery. If omitted, the server generates one and returns it once in
     * the create response. num_retries is 0-10; retry_interval_seconds and
     * retry_timeout_seconds are >=1.
     *
     * @param array{name: string, subgraph_name: string, subgraph_version: string, entity: string, webhook_url: string, secret?: string, num_retries?: int, retry_interval_seconds?: int, retry_timeout_seconds?: int} $req
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks/operation/createWebhook
     */
    public function create(array $req): array
    {
        $this->validateResourceName('webhook', (string) ($req['name'] ?? ''));
        if (strlen((string) $req['name']) > 42) {
            throw new GoldskyException('goldsky: webhook name must be at most 42 characters');
        }
        $this->validateSubgraphTarget((string) ($req['subgraph_name'] ?? ''), (string) ($req['subgraph_version'] ?? ''));
        if (trim((string) ($req['entity'] ?? '')) === '') {
            throw new GoldskyException('goldsky: webhook entity is required');
        }
        $url = parse_url((string) ($req['webhook_url'] ?? ''));
        if (!is_array($url) || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host'])) {
            throw new GoldskyException('goldsky: webhook URL must be an absolute HTTP(S) URL');
        }
        if (isset($req['num_retries']) && ($req['num_retries'] < 0 || $req['num_retries'] > 10)) {
            throw new GoldskyException("num_retries must be between 0 and 10, got {$req['num_retries']}");
        }
        if (isset($req['retry_interval_seconds']) && $req['retry_interval_seconds'] < 1) {
            throw new GoldskyException('retry_interval_seconds must be >= 1');
        }
        if (isset($req['retry_timeout_seconds']) && $req['retry_timeout_seconds'] < 1) {
            throw new GoldskyException('retry_timeout_seconds must be >= 1');
        }
        [, $body] = $this->requester->request('POST', ['subgraphs', 'webhooks'], [], $req);
        return $this->decode($body);
    }

    /**
     * Deletes a webhook by name.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks/operation/deleteWebhook
     */
    public function delete(string $name): void
    {
        $this->validateResourceName('webhook', $name);
        $this->requester->request('DELETE', ['subgraphs', 'webhooks', $name]);
    }
}
