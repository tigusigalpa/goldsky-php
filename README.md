# Goldsky PHP/Laravel Client/SDK/Library

![Goldsky API PHP Laravel SDK Client](https://i.postimg.cc/P51hthPk/goldsky-php-laravel-sdk-client.jpg)

[![Tests](https://github.com/tigusigalpa/goldsky-php/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/goldsky-php/actions/workflows/ci.yml)
[![Coverage](https://github.com/tigusigalpa/goldsky-php/actions/workflows/coverage.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/goldsky-php/actions/workflows/coverage.yml)
[![CodeQL](https://github.com/tigusigalpa/goldsky-php/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/tigusigalpa/goldsky-php/actions/workflows/codeql.yml)
[![Codecov](https://codecov.io/gh/tigusigalpa/goldsky-php/graph/badge.svg)](https://codecov.io/gh/tigusigalpa/goldsky-php)
[![Latest Release](https://img.shields.io/github/v/release/tigusigalpa/goldsky-php?display_name=tag)](https://github.com/tigusigalpa/goldsky-php/releases)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A58.1-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

`goldsky-php` is a practical PHP and Laravel client for [Goldsky](https://goldsky.com).
Use it when your application needs to manage pipelines, subgraphs, webhooks, and
Edge endpoints — or simply query a subgraph and make an EVM JSON-RPC call.

The package keeps the boring but important parts in one place: authentication,
pagination, retries, error handling, URL escaping, response-size limits, and
Laravel service-container integration. You write the application logic; the SDK
takes care of speaking Goldsky.

> This is a community-maintained SDK, not an official Goldsky package.

## At a glance

| I want to… | Start here |
| --- | --- |
| Manage pipelines, subgraphs, webhooks, or Edge endpoints | `new Client($projectToken)` |
| Query a **public** subgraph | `Client::forData()->graphQL->queryPublic()` |
| Query a **private** subgraph | `$client->graphQL->queryPrivate()` |
| Call an Edge RPC endpoint | `Client::forData($config)->rpc->call()` |
| Use it in Laravel | Publish the config, then inject `Client` or use `Goldsky` |
| Understand every REST operation | [API coverage map](docs/api-coverage.md) |

Built against Goldsky REST API **v1.2.0**: all 40 documented REST operations,
plus Subgraph GraphQL, Edge JSON-RPC, and webhook verification.

## Installation

```bash
composer require tigusigalpa/goldsky-php
```

The library requires PHP 8.1+ and works with or without Laravel.

## Your first successful request

Set a project token, then ask Goldsky for one page of pipelines:

```bash
export GOLDSKY_API_KEY=your-project-bearer-token
```

```php
<?php

use Tigusigalpa\Goldsky\Client;

$client = new Client((string) getenv('GOLDSKY_API_KEY'));

$page = $client->pipelines->list(['page_size' => 20]);

foreach ($page->data as $pipeline) {
    printf("%s — %s\n", $pipeline['name'] ?? '(unnamed)', $pipeline['status'] ?? 'unknown');
}
```

That is the basic shape of most REST calls: select a service from `$client`,
call a readable method, and receive plain PHP arrays or a `Page`.

## Authentication: which secret belongs where?

Goldsky uses two different credentials. Keeping them separate avoids a lot of
confusion:

| Credential | Used for | SDK behaviour |
| --- | --- | --- |
| Project API token | REST control plane and private GraphQL | Sent as `Authorization: Bearer …` |
| Edge endpoint API key | Edge JSON-RPC | Sent as `X-ERPC-Secret-Token` |

Never put either secret in a browser bundle, a repository, or a manually built
URL. The SDK keeps the Edge secret out of URLs and error messages.

### A REST client

Use a normal `Client` when the application manages project resources or makes
private GraphQL requests:

```php
use Tigusigalpa\Goldsky\Client;

$client = new Client($_ENV['GOLDSKY_API_KEY']);
```

### A data-only client

Public GraphQL and Edge RPC do not need a REST project token. This is useful in
a small worker that only reads data:

```php
use Tigusigalpa\Goldsky\Client;
use Tigusigalpa\Goldsky\Config;

$client = Client::forData(
    (new Config())->withEdgeAPIKey($_ENV['GOLDSKY_EDGE_API_KEY']),
);
```

REST and private GraphQL calls on a data-only client fail locally before a
request is sent.

## Configuration you will actually use

Defaults are safe for most applications: a 60-second timeout, TLS verification,
three attempts for safe reads, and a 16 MiB response limit. Override only what
your environment needs:

```php
use Tigusigalpa\Goldsky\Client;
use Tigusigalpa\Goldsky\Config;

$config = (new Config())
    ->withTimeout(15.0)
    ->withRetryMaxAttempts(4)
    ->withUserAgent('my-indexer/1.0')
    ->withMaxResponseBodyBytes(32 * 1024 * 1024);

$client = new Client($_ENV['GOLDSKY_API_KEY'], $config);
```

Only safe reads are retried automatically. Mutations are deliberately not
retried because Goldsky does not document idempotency keys. If you explicitly
accept that risk, opt in:

```php
$config->withRetryMutations(true);
```

Streaming subgraph deployments are never retried automatically, even with that
setting enabled, because a file stream cannot safely be replayed.

## Pipelines

Pipelines are easiest to work with in a small lifecycle: validate, create,
observe, then pause/resume or delete when your application no longer needs it.

### List or paginate pipelines

```php
// One page. page_size may be 1–200; omit it to use Goldsky's default.
$page = $client->pipelines->list([
    'type' => 'realtime',
    'page_size' => 50,
]);

foreach ($page->data as $pipeline) {
    echo $pipeline['name'] . "\n";
}
```

```php
// Every page. Stop on next_page_token, not on a short page.
$pager = $client->pipelines->newPager(['page_size' => 50]);

while (!$pager->isDone()) {
    $page = $pager->nextPage();

    foreach ($page->data as $pipeline) {
        // Save, display, or enqueue the pipeline.
    }
}
```

### Validate before creating

Use validation while authoring a pipeline. Goldsky can return structured errors
and warnings without creating anything:

```php
$definition = [
    'sources' => [
        'incoming-events' => [
            'type' => 'webhook',
            'options' => ['url' => 'https://example.com/source'],
        ],
    ],
    'transforms' => [],
    'sinks' => [
        'archive' => [
            'type' => 'webhook',
            'options' => ['url' => 'https://example.com/sink'],
        ],
    ],
];

$check = $client->pipelines->validate([
    'name' => 'event-archive',
    'definition' => $definition,
]);

foreach ($check['errors'] ?? [] as $error) {
    printf("%s: %s\n", $error['field'] ?? 'pipeline', $error['message']);
}
```

### Create and preview a pipeline

```php
$pipeline = $client->pipelines->create([
    'name' => 'event-archive',
    'resource_size' => 'small',
    'definition' => $definition,
]);

printf("Created %s (%s)\n", $pipeline['name'], $pipeline['status']);
```

```php
// A preview lives for 1–600 seconds.
$preview = $client->pipelines->preview([
    'definition' => $definition,
    'ttl_seconds' => 300,
]);

echo 'Preview: ' . ($preview['pipeline_name'] ?? 'ready') . "\n";
```

### Inspect, operate, and troubleshoot

```php
$name = 'event-archive';

$pipeline = $client->pipelines->get($name);
$status = $client->pipelines->status($name);
$state = $client->pipelines->state($name); // Goldsky's open state JSON is in $state['data'].

$logs = $client->pipelines->logs($name, [
    'logLevels' => 'error',
    'direction' => 'desc',
    'search' => 'timeout',
]);

$errors = $client->pipelines->errorCount($name, 24);

$client->pipelines->pause($name);
$client->pipelines->resume($name);
$client->pipelines->restart($name, ['clearState' => true]);
```

```php
// Destructive: remove a pipeline only when you are sure it is no longer needed.
$client->pipelines->delete('event-archive');
```

## Subgraphs

### Discover subgraphs and supported chains

```php
$chains = $client->catalogs->supportedSubgraphChains();
$page = $client->subgraphs->list(['page_size' => 25]);

foreach ($page->data as $subgraph) {
    printf("%s / %s [%s]\n", $subgraph['name'], $subgraph['version'], $subgraph['status']);
}
```

```php
// Fetch a subgraph, a deployed version, or a tag such as "latest".
$subgraph = $client->subgraphs->get('dex-analytics');
$version = $client->subgraphs->getVersion('dex-analytics', 'v1');
```

### Change endpoint settings and read logs

```php
$updated = $client->subgraphs->updateVersion('dex-analytics', 'v1', [
    'public_endpoint_enabled' => true,
    'private_endpoint_enabled' => true,
    'description' => 'Indexed swaps and liquidity events.',
]);

$logs = $client->subgraphs->logs('dex-analytics', 'v1', [
    'log_level' => 'error',
    'direction' => 'desc',
    'search' => 'mapping',
]);
```

### Deploy a compiled bundle

```php
$bundle = fopen(__DIR__ . '/build.zip', 'rb');

try {
    $deployment = $client->subgraphs->deploy('dex-analytics', 'v2', [
        'bundle' => $bundle,
        'bundle_filename' => 'build.zip',
        'start_block' => '21000000',
        'description' => 'Version 2 of the analytics subgraph.',
    ]);
} finally {
    fclose($bundle);
}
```

`overwrite=1` is rejected by Goldsky. To replace a deployment, create a new
version, move a tag, or delete the old deployment after removing its references.

### Tags and lifecycle controls

```php
// Point the "latest" tag to a deployed version.
$client->subgraphs->setTag('dex-analytics', 'latest', [
    'target_version' => 'v2',
]);

$client->subgraphs->pause('dex-analytics', 'v2');
$client->subgraphs->resume('dex-analytics', 'v2');

// These are destructive operations; perform them only after checking references.
$client->subgraphs->deleteTag('dex-analytics', 'old');
$client->subgraphs->deleteDeployment('dex-analytics', 'v1');
```

### Find webhook-able entities

```php
$entities = $client->subgraphs->webhookEntities('dex-analytics', 'v2');

foreach ($entities['data']['entities'] ?? [] as $entity) {
    echo $entity . "\n";
}
```

## Subgraph webhooks

Create a webhook once, store its delivery secret immediately, then verify every
incoming request before reading its JSON body.

```php
$webhook = $client->webhooks->create([
    'name' => 'swap-events',
    'subgraph_name' => 'dex-analytics',
    'subgraph_version' => 'v2',
    'entity' => 'Swap',
    'webhook_url' => 'https://app.example.com/webhooks/goldsky/swaps',
    'num_retries' => 3,
    'retry_interval_seconds' => 10,
    'retry_timeout_seconds' => 30,
]);

// Goldsky may return a generated secret only once. Put it in your secret store.
$deliverySecret = $webhook['data']['webhook_secret'] ?? null;
```

```php
$allWebhooks = $client->webhooks->list();
$client->webhooks->delete('swap-events'); // destructive
```

### Verify an incoming webhook in Laravel

```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tigusigalpa\Goldsky\Webhook\WebhookVerifier;

Route::post('/webhooks/goldsky/swaps', function (Request $request) {
    $provided = (string) $request->header('goldsky-webhook-secret', '');
    $expected = (string) config('services.goldsky.webhook_secret');

    abort_unless(WebhookVerifier::verifySecret($provided, $expected), 401);

    $event = $request->json()->all();
    // Process $event after verification.

    return response()->noContent();
});
```

Goldsky documents a secret header, not an HMAC signature. The verifier uses
`hash_equals` for a constant-time comparison.

## Edge endpoints

### Explore the catalog and create an endpoint

```php
$networks = $client->catalogs->edgeNetworks();
$sources = $client->catalogs->edgeSources();

$endpoint = $client->edge->create([
    'name' => 'app-rpc',
    'product' => 'rpc',
    'allowed_domains' => ['https://app.example.com'],
]);

// The API key can be returned once at creation. Store it safely.
$edgeKey = $endpoint['data']['api_key'] ?? null;
```

### Update, monitor, and control an endpoint

```php
$endpoint = $client->edge->get('app-rpc');

$endpoint = $client->edge->update('app-rpc', [
    'allowed_domains' => [
        'https://app.example.com',
        'https://staging.example.com',
    ],
]);

// An empty list clears the allowlist. null explicitly clears the rate-limit budget.
$endpoint = $client->edge->update('app-rpc', [
    'allowed_domains' => [],
    'rate_limit_budget' => null,
]);

$metrics = $client->edge->metrics('app-rpc', [
    'from' => '2026-09-01T00:00:00Z',
    'to' => '2026-09-02T00:00:00Z',
    'bucket_size' => '1h',
]);

$client->edge->pause('app-rpc');
$client->edge->resume('app-rpc');
```

```php
// Use this only when you need to recover the key. Treat the result as a secret.
$recovered = $client->edge->revealKey('app-rpc');
$key = $recovered['data']['api_key'] ?? null;

// Destructive.
$client->edge->delete('app-rpc');
```

## Data planes

### Public and private Subgraph GraphQL

```php
// Public endpoint: no project API token required.
$dataClient = Client::forData();

$response = $dataClient->graphQL->queryPublic('your-project-id', 'dex-analytics', 'latest', [
    'query' => <<<'GRAPHQL'
        query LatestSwaps($first: Int!) {
          swaps(first: $first, orderBy: timestamp, orderDirection: desc) {
            id
            amount0
            amount1
            timestamp
          }
        }
        GRAPHQL,
    'variables' => ['first' => 10],
    'operationName' => 'LatestSwaps',
]);

if ($dataClient->graphQL->hasErrors($response)) {
    foreach ($response['errors'] as $error) {
        error_log($error['message']);
    }
} else {
    $swaps = $response['data']['swaps'] ?? [];
}
```

```php
// Private endpoint: use the REST-token client.
$response = $client->graphQL->queryPrivate('your-project-id', 'dex-analytics', 'v2', [
    'query' => '{ _meta { block { number } } }',
]);
```

GraphQL application errors live in the response's `errors` array. HTTP failures
still throw `ProblemDetails` or `TransportException`.

### Edge JSON-RPC: one call or many

```php
use Tigusigalpa\Goldsky\Client;
use Tigusigalpa\Goldsky\Config;

$rpc = Client::forData(
    (new Config())->withEdgeAPIKey($_ENV['GOLDSKY_EDGE_API_KEY']),
)->rpc;

$blockNumber = null;
$rpc->call(1, 'eth_blockNumber', null, $blockNumber);

printf("Latest Ethereum block: %s\n", $blockNumber);
```

```php
$calls = [
    ['method' => 'eth_blockNumber'],
    ['method' => 'eth_chainId'],
    ['method' => 'eth_getBalance', 'params' => ['0x0000000000000000000000000000000000000000', 'latest']],
];

$responses = $rpc->batch(1, $calls);

foreach ($responses as $response) {
    if (isset($response['error'])) {
        printf("RPC error %d: %s\n", $response['error']['code'], $response['error']['message']);
        continue;
    }

    var_dump($response['result']);
}
```

The client validates JSON-RPC versions, IDs, and result/error envelopes so an
unexpected proxy or malformed response does not quietly become application data.

## Errors you can act on

There are two error families:

- `ProblemDetails`: Goldsky returned a non-2xx RFC 9457 problem response.
- `TransportException`: a network failure, an invalid response, a response that
  exceeds the configured limit, or malformed GraphQL/JSON-RPC data.

```php
use Tigusigalpa\Goldsky\Exceptions\ProblemDetails;
use Tigusigalpa\Goldsky\Exceptions\TransportException;

try {
    $pipeline = $client->pipelines->get('event-archive');
} catch (ProblemDetails $error) {
    if ($error->isNotFound()) {
        // Create it, show an empty state, or return a 404 from your app.
    } elseif ($error->isRateLimited()) {
        [$seconds, $hasValue] = $error->retryAfter();
        if ($hasValue) {
            sleep($seconds);
        }
    } elseif ($error->isValidation()) {
        foreach ($error->getErrors() as $item) {
            printf("%s: %s\n", $item['field'] ?? 'request', $item['message']);
        }
    } else {
        throw $error;
    }
} catch (TransportException $error) {
    // Log it and let your job framework decide whether to retry the work.
    error_log($error->getMessage());
}
```

Branch on `getType()` when you need a stable, exact error identity. `title` and
`detail` are meant for people and may change.

## Laravel

Laravel package discovery registers the service provider automatically.

```bash
php artisan vendor:publish --tag=goldsky-config
```

Add the secrets to `.env`:

```dotenv
GOLDSKY_API_KEY=your-project-bearer-token
GOLDSKY_EDGE_API_KEY=your-edge-api-key
GOLDSKY_TIMEOUT=15
GOLDSKY_RETRY_MAX_ATTEMPTS=3
```

### Use the facade

```php
use Tigusigalpa\Goldsky\Laravel\Facades\Goldsky;

$pipelines = Goldsky::pipelines()->list(['page_size' => 50]);
$subgraph = Goldsky::subgraphs()->getVersion('dex-analytics', 'latest');
```

### Or inject the client

```php
use Tigusigalpa\Goldsky\Client;

final class PipelineController
{
    public function __construct(private Client $goldsky)
    {
    }

    public function index(): array
    {
        return $this->goldsky->pipelines->list(['page_size' => 50])->data;
    }
}
```

## Every REST method, in one place

The examples above cover the usual paths. These are the remaining entry points
you can compose into your own workflows:

| Area | Methods |
| --- | --- |
| Pipelines | `list`, `newPager`, `create`, `get`, `delete`, `validate`, `preview`, `pause`, `resume`, `restart`, `logs`, `errorCount`, `status`, `state` |
| Subgraphs | `list`, `newPager`, `get`, `supportedChains`, `getVersion`, `updateVersion`, `logs`, `pause`, `resume`, `setTag`, `deleteTag`, `deleteDeployment`, `deploy`, `webhookEntities` |
| Webhooks | `list`, `create`, `delete` |
| Edge | `list`, `newPager`, `create`, `get`, `update`, `delete`, `pause`, `resume`, `revealKey`, `metrics` |
| Catalogs | `supportedSubgraphChains`, `edgeNetworks`, `edgeSources` |

For every operation's request shape, response type, test, and Goldsky API link,
see the [API coverage map](docs/api-coverage.md).

## Runnable examples and references

- [8 runnable examples](examples/README.md) for a quick terminal-first tour.
- [Upgrade guide](docs/upgrading.md) for behavioural changes between versions.
- [Security guide](docs/security.md) for secrets, retries, TLS, and response limits.
- [Goldsky REST API](https://api.goldsky.com/api/v1/docs)
- [Goldsky error catalogue](https://api.goldsky.com/api/errors)

## Development

```bash
composer install
composer validate --strict --no-check-publish
vendor/bin/phpunit
find src config examples tests -name '*.php' -print0 | xargs -0 -n1 php -l
```

## License

MIT — see [LICENSE](LICENSE).

Copyright (c) 2026 Igor Sazonov
