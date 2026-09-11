# goldsky-php

[![PHP](https://img.shields.io/badge/PHP-%E2%89%A58.1-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

PHP/Laravel SDK for the [Goldsky](https://goldsky.com) REST control plane and
the Subgraph GraphQL and Edge JSON-RPC data planes.

Built against Goldsky REST API **v1.2.0** (40 operations, OpenAPI 3.1.0). The
live spec was fetched from <https://api.goldsky.com/api/v1/docs/openapi.json>
and diffed against the operation manifest — **no delta was found**.

## Features

- **40 REST operations** across Pipelines, Subgraphs, Webhooks, Edge endpoints,
  and Catalogs — full coverage of the live OpenAPI contract.
- **GraphQL Subgraph data plane** — public and private (Bearer-authenticated)
  endpoints.
- **Edge JSON-RPC data plane** — single and batch requests over HTTPS.
- **Webhook verification** — constant-time `goldsky-webhook-secret` check.
- **RFC 9457 problem errors** — classify failures by stable `type` URI.
- **Retry with backoff** — safe reads retried by default; mutations not retried
  unless opted in; honours `Retry-After`.
- **Credential redaction** — tokens and API keys never appear in errors or logs.
- **Laravel integration** — service provider, facade, and publishable config.
- **Framework-agnostic** — usable in any PHP 8.1+ project via Composer.

## Installation

```bash
composer require tigusigalpa/goldsky-php
```

## Quick start

### Direct PHP

```php
use Tigusigalpa\Goldsky\Client;

$client = new Client(getenv('GOLDSKY_API_KEY'));

// List pipelines
$page = $client->pipelines->list(['page_size' => 50]);
foreach ($page->data as $pipeline) {
    echo $pipeline['name'] . "\n";
}

// Paginate subgraphs
$pager = $client->subgraphs->newPager(['page_size' => 25]);
while (true) {
    $page = $pager->nextPage();
    foreach ($page->data as $subgraph) {
        echo $subgraph['name'] . '/' . $subgraph['version'] . "\n";
    }
    if (!$page->hasMore()) break;
}

// Edge RPC
$client->setEdgeAPIKey(getenv('GOLDSKY_EDGE_API_KEY'));
$block = null;
$client->rpc->call(1, 'eth_blockNumber', null, $block);
echo "Latest block: $block\n";
```

### Laravel

Publish the config:

```bash
php artisan vendor:publish --tag=goldsky-config
```

Set environment variables in `.env`:

```env
GOLDSKY_API_KEY=your-project-bearer-token
GOLDSKY_EDGE_API_KEY=your-edge-api-key
```

Use the facade:

```php
use Tigusigalpa\Goldsky\Laravel\Facades\Goldsky;

$page = Goldsky::pipelines()->list(['page_size' => 50]);
```

Or inject the client:

```php
use Tigusigalpa\Goldsky\Client;

class PipelineController
{
    public function __construct(private Client $goldsky) {}

    public function index()
    {
        return $this->goldsky->pipelines->list(['page_size' => 50]);
    }
}
```

## Authentication

The REST project API token is scoped to a single Goldsky project and sent as
`Authorization: Bearer <token>`. The Edge endpoint API key is a separate secret
carried in the Edge RPC query string. Both are kept private and never appear in
error messages or logs.

## Error handling

All REST failures throw `ProblemDetails` (RFC 9457 `application/problem+json`).
Branch on `getType()` (a stable URI), not on `getTitle()` or `getDetail()`
(human-readable prose that may change):

```php
use Tigusigalpa\Goldsky\Exceptions\ProblemDetails;

try {
    $client->pipelines->get('my-pipe');
} catch (ProblemDetails $e) {
    if ($e->isNotFound()) {
        // 404
    } elseif ($e->isRateLimited()) {
        [$secs, $ok] = $e->retryAfter();
        if ($ok) sleep($secs);
    }
}
```

Transport-level failures (network errors, malformed responses) throw
`TransportException`. Both extend `GoldskyException`.

## Pagination

List endpoints return a `Page` with `pagination.next_page_token`. Use the
pager for full iteration. A page can hold fewer than `page_size` items and
still have a next page, so completion is inferred from `next_page_token`
alone:

```php
$pager = $client->pipelines->newPager(['page_size' => 50]);
while (true) {
    $page = $pager->nextPage();
    // process $page->data
    if (!$page->hasMore()) break;
}
```

## Retry behavior

By default only safe reads (GET, HEAD, OPTIONS) are retried on transport errors
and status codes 429, 500, 502, 503, and 504, using capped exponential backoff
with jitter and honouring `Retry-After`. Mutations are not retried automatically
because Goldsky does not document idempotency keys. Opt in to unsafe mutation
retry via `Config::withRetryMutations(true)` or `GOLDSKY_RETRY_MUTATIONS=true`.

## Data planes

### GraphQL Subgraph

```php
$response = $client->graphQL->queryPrivate($projectID, 'my-subgraph', 'latest', [
    'query' => '{ _meta { block { number } } }',
]);
if ($client->graphQL->hasErrors($response)) {
    foreach ($response['errors'] as $err) {
        echo "GraphQL error: {$err['message']}\n";
    }
}
```

### Edge JSON-RPC

```php
$client->setEdgeAPIKey(getenv('GOLDSKY_EDGE_API_KEY'));

// Single call
$block = null;
$client->rpc->call(1, 'eth_blockNumber', null, $block);

// Batch call
$calls = [
    ['method' => 'eth_blockNumber'],
    ['method' => 'eth_chainId'],
];
$client->rpc->batch(1, $calls);
```

### Webhook verification

```php
use Tigusigalpa\Goldsky\Webhook\WebhookVerifier;

if (!WebhookVerifier::verifyRequest($headers, $expectedSecret)) {
    http_response_code(401);
    exit;
}
```

## Multipart subgraph deployment

```php
$bundle = fopen('build.zip', 'r');
$client->subgraphs->deploy('my-subgraph', 'v1', [
    'bundle' => $bundle,
    'bundle_filename' => 'build.zip',
    'start_block' => '100',
    'description' => 'My subgraph',
]);
fclose($bundle);
```

The server rejects `overwrite=1`; to replace a version, delete it and deploy
again, or move a tag to it.

## Documentation

- [API Coverage](docs/api-coverage.md) — all 40 operations mapped to PHP methods
- [Upgrade Guide](docs/upgrading.md) — migration between releases
- [Security Guide](docs/security.md) — secrets, redaction, TLS, retry safety
- [Examples](examples/README.md) — 8 runnable examples
- [Goldsky API Docs](https://api.goldsky.com/api/v1/docs)
- [Goldsky Error Catalogue](https://api.goldsky.com/api/errors)

## Development

```bash
composer install
composer test
composer lint
```

## License

MIT — see [LICENSE](LICENSE).

Copyright (c) 2026 Igor Sazonov
