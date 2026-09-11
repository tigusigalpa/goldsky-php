<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\API;

use Tigusigalpa\Goldsky\Exceptions\GoldskyException;
use Tigusigalpa\Goldsky\Page;
use Tigusigalpa\Goldsky\Pager;

/**
 * Subgraphs manages Subgraphs and their versions, tags, and deployments.
 *
 * @see https://api.goldsky.com/api/v1/docs#tag/Subgraphs
 */
final class Subgraphs extends BaseAPI
{
    /**
     * Lists a single page of subgraphs.
     *
     * @param array{page_size?: int, page_token?: string} $opts
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraphs/operation/listSubgraphs
     */
    public function list(array $opts = []): Page
    {
        $this->validatePageSize($opts['page_size'] ?? 0);
        $query = [];
        if (($opts['page_size'] ?? 0) > 0) {
            $query['page_size'] = $opts['page_size'];
        }
        if (!empty($opts['page_token'])) {
            $query['page_token'] = $opts['page_token'];
        }
        [, $body] = $this->requester->request('GET', ['subgraphs'], $query);
        return Page::fromJSON($body);
    }

    /**
     * Returns a pager over subgraphs starting at opts.page_token.
     *
     * @param array{page_size?: int, page_token?: string} $opts
     */
    public function newPager(array $opts = []): Pager
    {
        $this->validatePageSize($opts['page_size'] ?? 0);
        return new Pager(
            fn (array $q) => $this->list(array_merge($opts, $q)),
            $opts['page_size'] ?? 0,
            $opts['page_token'] ?? '',
        );
    }

    /**
     * Fetches a subgraph with its versions and tags.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraphs/operation/getSubgraph
     */
    public function get(string $name): Page
    {
        $this->validateSubgraphTarget($name);
        [, $body] = $this->requester->request('GET', ['subgraphs', $name]);
        return Page::fromJSON($body);
    }

    /**
     * Lists supported deployment chains.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Catalogs/operation/listSubgraphChains
     */
    public function supportedChains(): array
    {
        [, $body] = $this->requester->request('GET', ['subgraphs', 'supported-chains']);
        return $this->decode($body);
    }

    /**
     * Fetches a subgraph tag or deployed version.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraphs/operation/getSubgraphVersion
     */
    public function getVersion(string $name, string $version): Page
    {
        $this->validateSubgraphTarget($name, $version);
        [, $body] = $this->requester->request('GET', ['subgraphs', $name, $version]);
        return Page::fromJSON($body);
    }

    /**
     * Updates endpoint settings on a version or tag.
     *
     * @param array{public_endpoint_enabled?: bool, private_endpoint_enabled?: bool, description?: string} $req
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Lifecycle/operation/updateSubgraphVersion
     */
    public function updateVersion(string $name, string $version, array $req): array
    {
        $this->validateSubgraphTarget($name, $version);
        [, $body] = $this->requester->request('PATCH', ['subgraphs', $name, $version], [], $req);
        $decoded = $this->decode($body);
        return $decoded['data'] ?? $decoded;
    }

    /**
     * Fetches a page of subgraph indexing logs.
     *
     * @param array{cursor?: float, after?: float, direction?: string, search?: string, log_level?: string, log_levels?: string} $opts
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Logs/operation/getSubgraphLogs
     */
    public function logs(string $name, string $version, array $opts = []): array
    {
        $this->validateSubgraphTarget($name, $version);
        $query = [];
        if (isset($opts['cursor'])) {
            $query['cursor'] = $opts['cursor'];
        }
        if (isset($opts['after'])) {
            $query['after'] = $opts['after'];
        }
        if (!empty($opts['direction'])) {
            $query['direction'] = $opts['direction'];
        }
        if (!empty($opts['search'])) {
            $query['search'] = $opts['search'];
        }
        if (!empty($opts['log_level'])) {
            $query['log_level'] = $opts['log_level'];
        }
        if (!empty($opts['log_levels'])) {
            $query['log_levels'] = $opts['log_levels'];
        }
        [, $body] = $this->requester->request('GET', ['subgraphs', $name, $version, 'logs'], $query);
        return $this->decode($body);
    }

    /**
     * Pauses a deployed subgraph version. Pause/resume targets a deployed
     * version, not a moving tag.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Lifecycle/operation/pauseSubgraph
     */
    public function pause(string $name, string $version): void
    {
        $this->validateSubgraphTarget($name, $version);
        $this->requester->request('PUT', ['subgraphs', $name, $version, 'pause']);
    }

    /**
     * Resumes a paused deployed subgraph version.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Lifecycle/operation/resumeSubgraph
     */
    public function resume(string $name, string $version): void
    {
        $this->validateSubgraphTarget($name, $version);
        $this->requester->request('PUT', ['subgraphs', $name, $version, 'resume']);
    }

    /**
     * Creates or moves a tag to a target version.
     *
     * @param array{target_version: string} $req
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Tags/operation/setSubgraphTag
     */
    public function setTag(string $name, string $version, array $req): array
    {
        $this->validateSubgraphTarget($name, $version);
        if (!isset($req['target_version']) || !is_string($req['target_version']) || !preg_match('/^[a-zA-Z0-9][\\w+.-]*$/', $req['target_version'])) {
            throw new GoldskyException('invalid target subgraph version');
        }
        [, $body] = $this->requester->request('PUT', ['subgraphs', $name, 'tags', $version], [], $req);
        $decoded = $this->decode($body);
        return $decoded['data'] ?? $decoded;
    }

    /**
     * Deletes a tag.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Tags/operation/deleteSubgraphTag
     */
    public function deleteTag(string $name, string $version): void
    {
        $this->validateSubgraphTarget($name, $version);
        $this->requester->request('DELETE', ['subgraphs', $name, 'tags', $version]);
    }

    /**
     * Deletes a deployment. This fails with HTTP 422 while the deployment is
     * referenced by a tag, pipeline, or webhook.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Deployments/operation/deleteSubgraphDeployment
     */
    public function deleteDeployment(string $name, string $version): void
    {
        $this->validateSubgraphTarget($name, $version);
        $this->requester->request('DELETE', ['subgraphs', $name, 'deployments', $version]);
    }

    /**
     * Deploys a compiled subgraph bundle as streaming multipart/form-data.
     *
     * The server accepts a maximum 50 MB compressed bundle and 100 MB extracted
     * bundle. overwrite=1 is rejected by the server; to replace a version,
     * delete it and deploy again, or move a tag to it.
     *
     * @param array{bundle: resource|string, bundle_filename: string, overwrite?: string, remove_graft?: string, skip_graft_validation?: string, start_block?: string, graft_from?: string, description?: string, graph_node_shard?: string} $opts
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Deployments/operation/deploySubgraph
     */
    public function deploy(string $name, string $version, array $opts): array
    {
        $this->validateSubgraphTarget($name, $version);
        if (empty($opts['bundle'])) {
            throw new GoldskyException('goldsky: Deploy requires a bundle');
        }
        if (empty($opts['bundle_filename'])) {
            throw new GoldskyException('goldsky: Deploy requires a bundle_filename');
        }
        if (($opts['overwrite'] ?? '') === '1') {
            throw new GoldskyException('goldsky: overwrite=1 is rejected by the server; delete the version and redeploy, or move a tag');
        }
        if (str_contains((string) $opts['bundle_filename'], "\r") || str_contains((string) $opts['bundle_filename'], "\n")) {
            throw new GoldskyException('goldsky: bundle_filename must not contain CR or LF');
        }

        $multipart = [];
        foreach (['overwrite', 'remove_graft', 'skip_graft_validation', 'start_block', 'graft_from', 'description', 'graph_node_shard'] as $field) {
            if (!empty($opts[$field])) {
                $multipart[] = ['name' => $field, 'contents' => $opts[$field]];
            }
        }
        $multipart[] = [
            'name' => 'bundle',
            'contents' => $opts['bundle'],
            'filename' => $opts['bundle_filename'],
            'headers' => ['Content-Type' => 'application/zip'],
        ];

        [, $body] = $this->requester->request('PUT', ['subgraphs', $name, 'deployments', $version], [], null, $multipart);
        $decoded = $this->decode($body);
        return $decoded['data'] ?? $decoded;
    }

    /**
     * Lists webhook-able entities for a subgraph version.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Subgraph%20Webhooks/operation/listWebhookEntities
     */
    public function webhookEntities(string $name, string $version): array
    {
        $this->validateSubgraphTarget($name, $version);
        [, $body] = $this->requester->request('GET', ['subgraphs', $name, $version, 'entities']);
        return $this->decode($body);
    }
}
