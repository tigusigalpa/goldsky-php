<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\API;

use Tigusigalpa\Goldsky\Exceptions\GoldskyException;
use Tigusigalpa\Goldsky\Page;
use Tigusigalpa\Goldsky\Pager;
use Tigusigalpa\Goldsky\Requester;

/**
 * Pipelines manages Turbo Pipelines.
 *
 * @see https://api.goldsky.com/api/v1/docs#tag/Pipelines
 */
final class Pipelines extends BaseAPI
{
    /**
     * Lists a single page of pipelines. Use newPager() for full iteration.
     *
     * @param array{type?: string, page_size?: int, page_token?: string} $opts
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipelines/operation/listPipelines
     */
    public function list(array $opts = []): Page
    {
        $this->validatePageSize($opts['page_size'] ?? 0);
        $query = [];
        if (!empty($opts['type'])) {
            $query['type'] = $opts['type'];
        }
        if (($opts['page_size'] ?? 0) > 0) {
            $query['page_size'] = $opts['page_size'];
        }
        if (!empty($opts['page_token'])) {
            $query['page_token'] = $opts['page_token'];
        }
        [, $body] = $this->requester->request('GET', ['pipelines'], $query);
        return Page::fromJSON($body);
    }

    /**
     * Returns a pager over pipelines starting at opts.page_token.
     *
     * @param array{type?: string, page_size?: int, page_token?: string} $opts
     */
    public function newPager(array $opts = []): Pager
    {
        $extra = [];
        if (!empty($opts['type'])) {
            $extra['type'] = $opts['type'];
        }
        return new Pager(
            fn (array $q) => $this->list(array_merge($opts, $q)),
            $opts['page_size'] ?? 0,
            $opts['page_token'] ?? '',
            $extra,
        );
    }

    /**
     * Creates a pipeline.
     *
     * @param array<string, mixed> $req
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipelines/operation/createPipeline
     */
    public function create(array $req): array
    {
        if (!empty($req['name'])) {
            $this->validatePipelineName($req['name']);
        }
        [, $body] = $this->requester->request('POST', ['pipelines'], [], $req);
        return $this->decode($body);
    }

    /**
     * Fetches a pipeline by name.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipelines/operation/getPipeline
     */
    public function get(string $name): array
    {
        $this->validatePipelineName($name);
        [, $body] = $this->requester->request('GET', ['pipelines', $name]);
        return $this->decode($body);
    }

    /**
     * Deletes a pipeline by name. Returns null on 204.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipelines/operation/deletePipeline
     */
    public function delete(string $name): void
    {
        $this->validatePipelineName($name);
        $this->requester->request('DELETE', ['pipelines', $name]);
    }

    /**
     * Validates a pipeline definition without creating it.
     *
     * @param array<string, mixed> $req
     *
     * @return array{valid: bool, errors: array<int, array{field?: string, message: string}>, warnings: array<int, array{field?: string, message: string}>}
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Authoring/operation/validatePipeline
     */
    public function validate(array $req): array
    {
        if (!empty($req['name'])) {
            $this->validatePipelineName($req['name']);
        }
        [, $body] = $this->requester->request('POST', ['pipelines', 'validate'], [], $req);
        return $this->decode($body);
    }

    /**
     * Previews a pipeline for a limited time.
     *
     * @param array<string, mixed> $req
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Authoring/operation/previewPipeline
     */
    public function preview(array $req): array
    {
        if (isset($req['ttl_seconds']) && $req['ttl_seconds'] !== '') {
            $ttl = (int) $req['ttl_seconds'];
            if ($ttl < 1 || $ttl > 600) {
                throw new GoldskyException("ttl_seconds must be between 1 and 600, got {$ttl}");
            }
        }
        [, $body] = $this->requester->request('POST', ['pipelines', 'preview'], [], $req);
        return $this->decode($body);
    }

    /**
     * Pauses a pipeline.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Lifecycle/operation/pausePipeline
     */
    public function pause(string $name): void
    {
        $this->validatePipelineName($name);
        $this->requester->request('PUT', ['pipelines', $name, 'pause']);
    }

    /**
     * Resumes a paused pipeline.
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Lifecycle/operation/resumePipeline
     */
    public function resume(string $name): void
    {
        $this->validatePipelineName($name);
        $this->requester->request('PUT', ['pipelines', $name, 'resume']);
    }

    /**
     * Restarts a pipeline, optionally clearing state.
     *
     * @param array{clearState?: bool}|null $req
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Lifecycle/operation/restartPipeline
     */
    public function restart(string $name, ?array $req = null): void
    {
        $this->validatePipelineName($name);
        $this->requester->request('PUT', ['pipelines', $name, 'restart'], [], $req);
    }

    /**
     * Fetches a page of pipeline logs.
     *
     * @param array{logLevels?: string, cursor?: float, after?: float, search?: string, direction?: string} $opts
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Logs/operation/getPipelineLogs
     */
    public function logs(string $name, array $opts = []): array
    {
        $this->validatePipelineName($name);
        $query = [];
        if (!empty($opts['logLevels'])) {
            $query['logLevels'] = $opts['logLevels'];
        }
        if (isset($opts['cursor'])) {
            $query['cursor'] = $opts['cursor'];
        }
        if (isset($opts['after'])) {
            $query['after'] = $opts['after'];
        }
        if (!empty($opts['search'])) {
            $query['search'] = $opts['search'];
        }
        if (!empty($opts['direction'])) {
            $query['direction'] = $opts['direction'];
        }
        [, $body] = $this->requester->request('GET', ['pipelines', $name, 'logs'], $query);
        return $this->decode($body);
    }

    /**
     * Fetches the pipeline error count for the last hours.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Logs/operation/getPipelineErrorCount
     */
    public function errorCount(string $name, int $sinceHours = 0): array
    {
        $this->validatePipelineName($name);
        if ($sinceHours !== 0 && ($sinceHours < 1 || $sinceHours > 168)) {
            throw new GoldskyException("since_hours must be between 1 and 168, got {$sinceHours}");
        }
        $query = [];
        if ($sinceHours > 0) {
            $query['since_hours'] = $sinceHours;
        }
        [, $body] = $this->requester->request('GET', ['pipelines', $name, 'logs', 'error-count'], $query);
        return $this->decode($body);
    }

    /**
     * Fetches the pipeline status.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Status/operation/getPipelineStatus
     */
    public function status(string $name): array
    {
        $this->validatePipelineName($name);
        [, $body] = $this->requester->request('GET', ['pipelines', $name, 'status']);
        return $this->decode($body);
    }

    /**
     * Fetches the pipeline state. The OpenAPI contract leaves the state schema
     * open, so the raw decoded JSON is returned.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Pipeline%20Status/operation/getPipelineState
     */
    public function state(string $name): array
    {
        $this->validatePipelineName($name);
        [, $body] = $this->requester->request('GET', ['pipelines', $name, 'state']);
        $decoded = $this->decode($body);
        // The state body may not be wrapped in {data:...}; fall back to raw.
        if (isset($decoded['data'])) {
            return $decoded;
        }
        if ($body !== '' && $decoded === []) {
            $raw = json_decode($body, true);
            return is_array($raw) ? ['data' => $raw] : ['data' => $body];
        }
        return $decoded;
    }
}
