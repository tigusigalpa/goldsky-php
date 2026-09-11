<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Tigusigalpa\Goldsky\Client;
use Tigusigalpa\Goldsky\Config;
use Tigusigalpa\Goldsky\Exceptions\GoldskyException;
use Tigusigalpa\Goldsky\Exceptions\ProblemDetails;
use Tigusigalpa\Goldsky\Webhook\WebhookVerifier;
use Tigusigalpa\Goldsky\Tests\TestCase;

/**
 * Edge-case tests: pagination, URL encoding, retries, redaction, problem
 * parsing, GraphQL, Edge RPC, webhook verification.
 */
final class EdgeCasesTest extends TestCase
{
    public function testPaginationContinuation(): void
    {
        [$client, $container] = $this->mockClientWithResponses([
            new Response(200, [], '{"data":[{"name":"p"}],"pagination":{"next_page_token":"tok1","page_size":50}}'),
            new Response(200, [], '{"data":[{"name":"p"}],"pagination":{"next_page_token":"tok2","page_size":50}}'),
            new Response(200, [], '{"data":[{"name":"p"}],"pagination":{"next_page_token":null,"page_size":50}}'),
        ]);

        $pager = $client->pipelines->newPager(['page_size' => 50]);
        $total = 0;
        for ($i = 0; $i < 10; $i++) {
            $page = $pager->nextPage();
            $total += count($page->data);
            if (!$page->hasMore()) {
                break;
            }
        }
        $this->assertSame(3, $total);
        $this->assertSame(3, count($container));
    }

    public function testUrlEncoding(): void
    {
        [$client, $container] = $this->mockClientWithResponses([
            new Response(200, [], '{"data":[],"pagination":{"next_page_token":null,"page_size":0}}'),
        ]);
        $client->subgraphs->get('my sub/graph');
        $req = $this->lastRequest($container);
        $this->assertStringContainsString('%20', $req['path']);
        $this->assertStringNotContainsString(' ', $req['path']);
    }

    public function testRetryAfter(): void
    {
        [$client, $container] = $this->mockClientWithResponses([
            new Response(429, ['Retry-After' => '0', 'Content-Type' => 'application/problem+json'], '{"type":"https://api.goldsky.com/api/errors/rate-limited","title":"Rate limited","status":429}'),
            new Response(429, ['Retry-After' => '0', 'Content-Type' => 'application/problem+json'], '{"type":"https://api.goldsky.com/api/errors/rate-limited","title":"Rate limited","status":429}'),
            new Response(200, [], '{"data":[],"pagination":{"next_page_token":null,"page_size":0}}'),
        ], ['retry_max_attempts' => 3]);

        $page = $client->pipelines->list();
        $this->assertSame([], $page->data);
        $this->assertSame(3, count($container));
    }

    public function testNoMutationRetry(): void
    {
        [$client, $container] = $this->mockClientWithResponses([
            new Response(500, ['Content-Type' => 'application/problem+json'], '{"type":"https://api.goldsky.com/api/errors/internal","title":"Internal","status":500}'),
        ], ['retry_max_attempts' => 3]);

        $this->expectException(ProblemDetails::class);
        try {
            $client->pipelines->create(['definition' => ['sources' => [], 'transforms' => [], 'sinks' => []]]);
        } finally {
            $this->assertSame(1, count($container));
        }
    }

    public function testMutationRetryOptIn(): void
    {
        [$client, $container] = $this->mockClientWithResponses([
            new Response(503, ['Retry-After' => '0'], '{"type":"x","title":"unavailable","status":503}'),
            new Response(200, [], '{"name":"p","type":"t","status":"RUNNING","definition":{"sources":{},"transforms":{},"sinks":{}},"created_at":"2026-01-01T00:00:00Z","updated_at":"2026-01-01T00:00:00Z"}'),
        ], ['retry_max_attempts' => 3, 'retry_mutations' => true]);

        $p = $client->pipelines->create(['definition' => ['sources' => [], 'transforms' => [], 'sinks' => []]]);
        $this->assertSame('p', $p['name']);
        $this->assertSame(2, count($container));
    }

    public function testProblemParsing(): void
    {
        [$client, , ] = $this->mockClient([
            [404, '{"type":"https://api.goldsky.com/api/errors/subgraph-not-found","title":"Subgraph not found","status":404,"detail":"No subgraph named x exists.","errors":[{"field":"name","message":"missing"}]}', ['Content-Type' => 'application/problem+json']],
        ]);
        try {
            $client->subgraphs->deleteDeployment('x', 'v1');
            $this->fail('expected ProblemDetails');
        } catch (ProblemDetails $e) {
            $this->assertSame('https://api.goldsky.com/api/errors/subgraph-not-found', $e->getType());
            $this->assertTrue($e->isNotFound());
            $this->assertCount(1, $e->getErrors());
            $this->assertSame('name', $e->getErrors()[0]['field']);
        }
    }

    public function testRedaction(): void
    {
        [$client, , ] = $this->mockClient([[500, '{"type":"x","title":"boom","status":500}']]);
        try {
            $client->pipelines->list();
            $this->fail('expected error');
        } catch (GoldskyException $e) {
            $this->assertStringNotContainsString('test-token', $e->getMessage());
        }
    }

    public function testMalformedJson(): void
    {
        [$client, , ] = $this->mockClient([[200, '{not json']]);
        $page = $client->pipelines->list();
        $this->assertSame([], $page->data);
    }

    public function testDeployRejectsOverwriteOne(): void
    {
        [$client, , $container] = $this->mockClient([[201, '{}']]);
        $this->expectException(GoldskyException::class);
        try {
            $client->subgraphs->deploy('s', 'v1', ['bundle' => 'x', 'bundle_filename' => 'build.zip', 'overwrite' => '1']);
        } finally {
            $this->assertSame(0, count($container));
        }
    }

    public function testWebhookVerifier(): void
    {
        $this->assertTrue(WebhookVerifier::verifySecret('s', 's'));
        $this->assertFalse(WebhookVerifier::verifySecret('s', 'x'));
        $this->assertFalse(WebhookVerifier::verifySecret('s', ''));
        $this->assertTrue(WebhookVerifier::verifyRequest(['goldsky-webhook-secret' => 's'], 's'));
        $this->assertFalse(WebhookVerifier::verifyRequest(['goldsky-webhook-secret' => 's'], 'x'));
    }

    public function testGraphQLErrorEnvelope(): void
    {
        [$client, ] = $this->mockClientWithResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"errors":[{"message":"field x is required","path":["x"]}],"data":null}'),
        ]);

        $resp = $client->graphQL->query('http://example.test/gn', ['query' => '{ x }'], false);
        $this->assertTrue($client->graphQL->hasErrors($resp));
        $this->assertSame('field x is required', $resp['errors'][0]['message']);
    }

    public function testGraphQLUrls(): void
    {
        [$client, , ] = $this->mockClient([[200, '{}']]);
        $pub = $client->graphQL->publicURL('proj', 'sub', 'v1');
        $priv = $client->graphQL->privateURL('proj', 'sub', 'v1');
        $this->assertStringEndsWith('/api/public/proj/subgraphs/sub/v1/gn', $pub);
        $this->assertStringEndsWith('/api/private/proj/subgraphs/sub/v1/gn', $priv);
    }

    public function testEdgeRpcSingle(): void
    {
        [$client, $container] = $this->mockClientWithResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"jsonrpc":"2.0","id":1,"result":"0x1234"}'),
        ], ['edge_api_key' => 'edge-key']);

        $result = null;
        $client->rpc->call(1, 'eth_blockNumber', null, $result);
        $this->assertSame('0x1234', $result);
        $uri = (string) $container[0]['request']->getUri();
        $this->assertStringContainsString('key=edge-key', $uri);
        $this->assertStringContainsString('/evm/1', $uri);
    }

    public function testEdgeRpcBatch(): void
    {
        [$client, ] = $this->mockClientWithResponses([
            new Response(200, ['Content-Type' => 'application/json'], '[{"jsonrpc":"2.0","id":1,"result":"eth_blockNumber"},{"jsonrpc":"2.0","id":2,"result":"eth_chainId"}]'),
        ], ['edge_api_key' => 'edge-key']);

        $calls = [
            ['method' => 'eth_blockNumber', 'result' => null],
            ['method' => 'eth_chainId', 'result' => null],
        ];
        $resps = $client->rpc->batch(1, $calls);
        $this->assertCount(2, $resps);
        $this->assertSame('eth_blockNumber', $calls[0]['result']);
        $this->assertSame('eth_chainId', $calls[1]['result']);
    }

    public function testEdgeRpcError(): void
    {
        [$client, ] = $this->mockClientWithResponses([
            new Response(200, ['Content-Type' => 'application/json'], '{"jsonrpc":"2.0","id":1,"error":{"code":-32601,"message":"method not found"}}'),
        ], ['edge_api_key' => 'edge-key']);

        $this->expectException(GoldskyException::class);
        $client->rpc->call(1, 'nope', null);
    }

    public function testEdgeRpcRequiresKey(): void
    {
        [$client, , ] = $this->mockClient([[200, '{}']]);
        $this->expectException(GoldskyException::class);
        $client->rpc->call(1, 'eth_blockNumber');
    }

    public function testNewClientRequiresToken(): void
    {
        $this->expectException(GoldskyException::class);
        new Client('');
    }

    public function testEdgeEndpointUrl(): void
    {
        [$client, , ] = $this->mockClient([[200, '{}']], ['edge_api_key' => 'ek']);
        $url = $client->rpc->endpointURL(137);
        $this->assertStringContainsString('/evm/137?', $url);
        $this->assertStringContainsString('key=ek', $url);
    }

    public function testRetryAfterParsing(): void
    {
        [$secs, $ok] = ProblemDetails::parseRetryAfter('5');
        $this->assertTrue($ok);
        $this->assertSame(5, $secs);

        [$secs, $ok] = ProblemDetails::parseRetryAfter('');
        $this->assertFalse($ok);

        [$secs, $ok] = ProblemDetails::parseRetryAfter('-1');
        $this->assertFalse($ok);
    }
}
