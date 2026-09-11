<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Tests\Unit;

use Tigusigalpa\Goldsky\Tests\TestCase;

/**
 * Contract tests for all 40 Goldsky REST operations.
 *
 * Each test asserts the HTTP method, encoded path, query/body shape, Bearer
 * header, and success decoding against a canned response.
 */
final class ContractTest extends TestCase
{
    public function testListPipelines(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":[],"pagination":{"next_page_token":null,"page_size":0}}'],
        ]);
        $page = $client->pipelines->list(['page_size' => 50]);
        $this->assertSame([], $page->data);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/pipelines', $req['path']);
        $this->assertStringContainsString('page_size=50', $req['query']);
        $this->assertBearer($req);
    }

    public function testCreatePipeline(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"name":"my-pipe","type":"t","status":"RUNNING","definition":{"sources":{},"transforms":{},"sinks":{}},"created_at":"2026-01-01T00:00:00Z","updated_at":"2026-01-01T00:00:00Z"}'],
        ]);
        $p = $client->pipelines->create(['name' => 'my-pipe', 'definition' => ['sources' => [], 'transforms' => [], 'sinks' => []]]);
        $this->assertSame('my-pipe', $p['name']);
        $req = $this->lastRequest($container);
        $this->assertSame('POST', $req['method']);
        $this->assertSame('/pipelines', $req['path']);
        $this->assertStringContainsString('"name":"my-pipe"', $req['body']);
        $this->assertBearer($req);
    }

    public function testGetPipeline(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"name":"my-pipe","type":"t","status":"RUNNING","definition":{"sources":{},"transforms":{},"sinks":{}},"created_at":"2026-01-01T00:00:00Z","updated_at":"2026-01-01T00:00:00Z"}'],
        ]);
        $p = $client->pipelines->get('my-pipe');
        $this->assertSame('my-pipe', $p['name']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/pipelines/my-pipe', $req['path']);
        $this->assertBearer($req);
    }

    public function testDeletePipeline(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->pipelines->delete('my-pipe');
        $req = $this->lastRequest($container);
        $this->assertSame('DELETE', $req['method']);
        $this->assertSame('/pipelines/my-pipe', $req['path']);
        $this->assertBearer($req);
    }

    public function testValidatePipeline(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"valid":true,"errors":[],"warnings":[]}'],
        ]);
        $r = $client->pipelines->validate(['definition' => ['sources' => [], 'transforms' => [], 'sinks' => []]]);
        $this->assertTrue($r['valid']);
        $req = $this->lastRequest($container);
        $this->assertSame('POST', $req['method']);
        $this->assertSame('/pipelines/validate', $req['path']);
        $this->assertBearer($req);
    }

    public function testPreviewPipeline(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"pipeline_name":"p","ttl_seconds":300,"expires_at":"2026-01-01T00:05:00Z"}'],
        ]);
        $r = $client->pipelines->preview(['definition' => ['sources' => [], 'transforms' => [], 'sinks' => []], 'ttl_seconds' => 300]);
        $this->assertSame('p', $r['pipeline_name']);
        $req = $this->lastRequest($container);
        $this->assertSame('POST', $req['method']);
        $this->assertSame('/pipelines/preview', $req['path']);
        $this->assertBearer($req);
    }

    public function testPausePipeline(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->pipelines->pause('my-pipe');
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/pipelines/my-pipe/pause', $req['path']);
        $this->assertBearer($req);
    }

    public function testResumePipeline(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->pipelines->resume('my-pipe');
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/pipelines/my-pipe/resume', $req['path']);
        $this->assertBearer($req);
    }

    public function testRestartPipeline(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->pipelines->restart('my-pipe', ['clearState' => true]);
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/pipelines/my-pipe/restart', $req['path']);
        $this->assertStringContainsString('"clearState":true', $req['body']);
        $this->assertBearer($req);
    }

    public function testGetPipelineLogs(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"results":[],"cursor":0}}'],
        ]);
        $client->pipelines->logs('my-pipe', ['cursor' => 123.0, 'direction' => 'desc', 'logLevels' => 'error']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/pipelines/my-pipe/logs', $req['path']);
        $this->assertStringContainsString('cursor=123', $req['query']);
        $this->assertStringContainsString('direction=desc', $req['query']);
        $this->assertStringContainsString('logLevels=error', $req['query']);
        $this->assertBearer($req);
    }

    public function testGetPipelineErrorCount(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"error_count":3}}'],
        ]);
        $r = $client->pipelines->errorCount('my-pipe', 24);
        $this->assertSame(3, (int) $r['data']['error_count']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/pipelines/my-pipe/logs/error-count', $req['path']);
        $this->assertStringContainsString('since_hours=24', $req['query']);
        $this->assertBearer($req);
    }

    public function testGetPipelineStatus(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"name":"my-pipe","status":"RUNNING","errors":[]}'],
        ]);
        $r = $client->pipelines->status('my-pipe');
        $this->assertSame('RUNNING', $r['status']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/pipelines/my-pipe/status', $req['path']);
        $this->assertBearer($req);
    }

    public function testGetPipelineState(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"checkpoint":"x"}}'],
        ]);
        $r = $client->pipelines->state('my-pipe');
        $this->assertSame('x', $r['data']['checkpoint']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/pipelines/my-pipe/state', $req['path']);
        $this->assertBearer($req);
    }

    public function testListSubgraphs(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":[],"pagination":{"next_page_token":null,"page_size":0}}'],
        ]);
        $page = $client->subgraphs->list(['page_size' => 25]);
        $this->assertSame([], $page->data);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/subgraphs', $req['path']);
        $this->assertBearer($req);
    }

    public function testGetSubgraph(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":[],"pagination":{"next_page_token":null,"page_size":0}}'],
        ]);
        $client->subgraphs->get('my-sub');
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/subgraphs/my-sub', $req['path']);
        $this->assertBearer($req);
    }

    public function testListSubgraphChains(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"supported_chains":["mainnet"]}}'],
        ]);
        $r = $client->subgraphs->supportedChains();
        $this->assertSame(['mainnet'], $r['data']['supported_chains']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/subgraphs/supported-chains', $req['path']);
        $this->assertBearer($req);
    }

    public function testGetSubgraphVersion(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":[],"pagination":{"next_page_token":null,"page_size":0}}'],
        ]);
        $client->subgraphs->getVersion('my-sub', 'v1');
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/subgraphs/my-sub/v1', $req['path']);
        $this->assertBearer($req);
    }

    public function testUpdateSubgraphVersion(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"name":"my-sub","version":"v1","status":"ACTIVE","network":"mainnet","health":"HEALTHY","synced":true,"graphql_endpoint":"","private_graphql_endpoint":"","public_endpoint_enabled":true,"private_endpoint_enabled":false,"description":null,"deployments":[]}}'],
        ]);
        $r = $client->subgraphs->updateVersion('my-sub', 'v1', ['public_endpoint_enabled' => true]);
        $this->assertSame('my-sub', $r['name']);
        $req = $this->lastRequest($container);
        $this->assertSame('PATCH', $req['method']);
        $this->assertSame('/subgraphs/my-sub/v1', $req['path']);
        $this->assertStringContainsString('"public_endpoint_enabled":true', $req['body']);
        $this->assertBearer($req);
    }

    public function testGetSubgraphLogs(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"results":[]}}'],
        ]);
        $client->subgraphs->logs('my-sub', 'v1', ['log_level' => 'error']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/subgraphs/my-sub/v1/logs', $req['path']);
        $this->assertStringContainsString('log_level=error', $req['query']);
        $this->assertBearer($req);
    }

    public function testPauseSubgraph(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->subgraphs->pause('my-sub', 'v1');
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/subgraphs/my-sub/v1/pause', $req['path']);
        $this->assertBearer($req);
    }

    public function testResumeSubgraph(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->subgraphs->resume('my-sub', 'v1');
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/subgraphs/my-sub/v1/resume', $req['path']);
        $this->assertBearer($req);
    }

    public function testSetSubgraphTag(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"name":"my-sub","version":"v1","status":"ACTIVE","network":"mainnet","health":"HEALTHY","synced":true,"graphql_endpoint":"","private_graphql_endpoint":"","public_endpoint_enabled":true,"private_endpoint_enabled":false,"description":null,"deployments":[]}}'],
        ]);
        $r = $client->subgraphs->setTag('my-sub', 'v1', ['target_version' => '1.0.0']);
        $this->assertSame('my-sub', $r['name']);
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/subgraphs/my-sub/tags/v1', $req['path']);
        $this->assertStringContainsString('"target_version":"1.0.0"', $req['body']);
        $this->assertBearer($req);
    }

    public function testDeleteSubgraphTag(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->subgraphs->deleteTag('my-sub', 'v1');
        $req = $this->lastRequest($container);
        $this->assertSame('DELETE', $req['method']);
        $this->assertSame('/subgraphs/my-sub/tags/v1', $req['path']);
        $this->assertBearer($req);
    }

    public function testDeleteSubgraphDeployment(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->subgraphs->deleteDeployment('my-sub', 'v1');
        $req = $this->lastRequest($container);
        $this->assertSame('DELETE', $req['method']);
        $this->assertSame('/subgraphs/my-sub/deployments/v1', $req['path']);
        $this->assertBearer($req);
    }

    public function testDeploySubgraph(): void
    {
        [$client, , $container] = $this->mockClient([
            [201, '{"data":{"name":"my-sub","version":"v1","status":"ACTIVE","network":"mainnet","health":"HEALTHY","synced":true,"graphql_endpoint":"","private_graphql_endpoint":"","public_endpoint_enabled":true,"private_endpoint_enabled":false,"description":null,"deployments":[]}}'],
        ]);
        $bundle = fopen('php://memory', 'r+');
        fwrite($bundle, str_repeat("\x50\x4b", 10));
        rewind($bundle);
        $r = $client->subgraphs->deploy('my-sub', 'v1', [
            'bundle' => $bundle, 'bundle_filename' => 'build.zip',
            'start_block' => '100', 'description' => 'd',
        ]);
        $this->assertSame('my-sub', $r['name']);
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/subgraphs/my-sub/deployments/v1', $req['path']);
        $this->assertStringStartsWith('multipart/form-data', $req['headers']['Content-Type'][0] ?? '');
        $this->assertStringContainsString('bundle', $req['body']);
        $this->assertStringContainsString('build.zip', $req['body']);
        $this->assertStringContainsString('start_block', $req['body']);
        $this->assertStringContainsString('description', $req['body']);
        $this->assertBearer($req);
    }

    public function testListWebhooks(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":[]}'],
        ]);
        $r = $client->webhooks->list();
        $this->assertSame([], $r['data']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/subgraphs/webhooks', $req['path']);
        $this->assertBearer($req);
    }

    public function testCreateWebhook(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"id":"wh-1","name":"wh","webhook_secret":"secret-value"}}'],
        ]);
        $r = $client->webhooks->create([
            'name' => 'wh', 'subgraph_name' => 'my-sub', 'subgraph_version' => 'v1',
            'entity' => 'Entity', 'webhook_url' => 'https://example.com/hook',
        ]);
        $this->assertSame('wh-1', $r['data']['id']);
        $req = $this->lastRequest($container);
        $this->assertSame('POST', $req['method']);
        $this->assertSame('/subgraphs/webhooks', $req['path']);
        $this->assertStringContainsString('"name":"wh"', $req['body']);
        $this->assertBearer($req);
    }

    public function testDeleteWebhook(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->webhooks->delete('wh');
        $req = $this->lastRequest($container);
        $this->assertSame('DELETE', $req['method']);
        $this->assertSame('/subgraphs/webhooks/wh', $req['path']);
        $this->assertBearer($req);
    }

    public function testListWebhookEntities(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"entities":[]}}'],
        ]);
        $r = $client->subgraphs->webhookEntities('my-sub', 'v1');
        $this->assertSame([], $r['data']['entities']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/subgraphs/my-sub/v1/entities', $req['path']);
        $this->assertBearer($req);
    }

    public function testListEdgeNetworks(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":[]}'],
        ]);
        $r = $client->catalogs->edgeNetworks();
        $this->assertSame([], $r['data']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/edge/networks', $req['path']);
        $this->assertBearer($req);
    }

    public function testListEdgeSources(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":[]}'],
        ]);
        $r = $client->catalogs->edgeSources();
        $this->assertSame([], $r['data']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/edge/sources', $req['path']);
        $this->assertBearer($req);
    }

    public function testListEdgeEndpoints(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":[],"pagination":{"next_page_token":null,"page_size":0}}'],
        ]);
        $page = $client->edge->list(['product' => 'rpc']);
        $this->assertSame([], $page->data);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/edge', $req['path']);
        $this->assertStringContainsString('product=rpc', $req['query']);
        $this->assertBearer($req);
    }

    public function testCreateEdgeEndpoint(): void
    {
        [$client, , $container] = $this->mockClient([
            [201, '{"data":{"name":"ep","product":"rpc","status":"ACTIVE","rate_limit_budget":null,"allowed_domains":[],"created_at":"2026-01-01T00:00:00Z","updated_at":"2026-01-01T00:00:00Z","paused_at":null,"api_key":"ek-123"},"warnings":[]}'],
        ]);
        $r = $client->edge->create(['name' => 'ep', 'product' => 'rpc']);
        $this->assertSame('ep', $r['data']['name']);
        $req = $this->lastRequest($container);
        $this->assertSame('POST', $req['method']);
        $this->assertSame('/edge', $req['path']);
        $this->assertBearer($req);
    }

    public function testGetEdgeEndpoint(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"name":"ep","product":"rpc","status":"ACTIVE","rate_limit_budget":null,"allowed_domains":[],"created_at":"2026-01-01T00:00:00Z","updated_at":"2026-01-01T00:00:00Z","paused_at":null}}'],
        ]);
        $r = $client->edge->get('ep');
        $this->assertSame('ep', $r['name']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/edge/ep', $req['path']);
        $this->assertBearer($req);
    }

    public function testUpdateEdgeEndpoint(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"name":"ep","product":"rpc","status":"ACTIVE","rate_limit_budget":null,"allowed_domains":["https://app.example.com"],"created_at":"2026-01-01T00:00:00Z","updated_at":"2026-01-01T00:00:00Z","paused_at":null}}'],
        ]);
        $r = $client->edge->update('ep', ['allowed_domains' => ['https://app.example.com']]);
        $this->assertSame('ep', $r['name']);
        $req = $this->lastRequest($container);
        $this->assertSame('PATCH', $req['method']);
        $this->assertSame('/edge/ep', $req['path']);
        $this->assertBearer($req);
    }

    public function testDeleteEdgeEndpoint(): void
    {
        [$client, , $container] = $this->mockClient([[204, '']]);
        $client->edge->delete('ep');
        $req = $this->lastRequest($container);
        $this->assertSame('DELETE', $req['method']);
        $this->assertSame('/edge/ep', $req['path']);
        $this->assertBearer($req);
    }

    public function testPauseEdgeEndpoint(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"name":"ep","product":"rpc","status":"PAUSED","rate_limit_budget":null,"allowed_domains":[],"created_at":"2026-01-01T00:00:00Z","updated_at":"2026-01-01T00:00:00Z","paused_at":"2026-01-01T00:00:00Z"}}'],
        ]);
        $r = $client->edge->pause('ep');
        $this->assertSame('PAUSED', $r['status']);
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/edge/ep/pause', $req['path']);
        $this->assertBearer($req);
    }

    public function testResumeEdgeEndpoint(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"name":"ep","product":"rpc","status":"ACTIVE","rate_limit_budget":null,"allowed_domains":[],"created_at":"2026-01-01T00:00:00Z","updated_at":"2026-01-01T00:00:00Z","paused_at":null}}'],
        ]);
        $r = $client->edge->resume('ep');
        $this->assertSame('ACTIVE', $r['status']);
        $req = $this->lastRequest($container);
        $this->assertSame('PUT', $req['method']);
        $this->assertSame('/edge/ep/resume', $req['path']);
        $this->assertBearer($req);
    }

    public function testRevealEdgeEndpointKey(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"api_key":"ek-123"}}'],
        ]);
        $r = $client->edge->revealKey('ep');
        $this->assertSame('ek-123', $r['data']['api_key']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/edge/ep/api-key', $req['path']);
        $this->assertBearer($req);
    }

    public function testGetEdgeEndpointMetrics(): void
    {
        [$client, , $container] = $this->mockClient([
            [200, '{"data":{"requests":[],"errors":[]}}'],
        ]);
        $r = $client->edge->metrics('ep', ['bucket_size' => '1h']);
        $this->assertSame([], $r['data']['requests']);
        $req = $this->lastRequest($container);
        $this->assertSame('GET', $req['method']);
        $this->assertSame('/edge/ep/metrics', $req['path']);
        $this->assertStringContainsString('bucket_size=1h', $req['query']);
        $this->assertBearer($req);
    }

    public function testContractCount(): void
    {
        // All 40 operations are covered by the tests above.
        $this->assertCount(40, array_filter(
            get_class_methods(self::class),
            fn (string $m) => str_starts_with($m, 'test') && $m !== 'testContractCount'
        ));
    }
}
