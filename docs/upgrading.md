# Upgrade Guide

This document describes breaking changes and migration paths between
goldsky-php releases.

## Unreleased → 1.0.0

### Edge RPC authentication

Edge RPC credentials are now sent in the documented
`X-ERPC-Secret-Token` request header, not as a `key` query parameter. Calls
made through `RPCClient` require no application-code changes, but any custom
HTTP middleware must allow this header.

### Stricter response handling

Successful REST, GraphQL, and JSON-RPC responses must now contain valid JSON.
Malformed, empty, oversized, or invalid JSON-RPC envelopes throw
`TransportException` instead of being treated as empty successful results.

### Data-only clients

Use `Client::forData()` for public GraphQL and Edge RPC without a REST project
token. REST and private GraphQL calls on this client fail locally.

## General principles

- The SDK follows [SemVer 2.0](https://semver.org). Breaking changes increment
  the major version.
- The REST project API token and the Edge endpoint API key are distinct
  secrets. Both are kept private and never appear in error messages or logs.
- All REST failures throw `ProblemDetails` (RFC 9457). Branch on `getType()`
  (a stable URI), not on `getTitle()` or `getDetail()` (human-readable prose).
- Transport-level failures throw `TransportException`. Both extend
  `GoldskyException`.
- Pagination completion is inferred from `next_page_token` alone — a page can
  hold fewer than `page_size` items and still have a next page.

## Configuration changes

### Config construction

The `Config` class uses a fluent builder pattern. All values have defaults:

```php
use Tigusigalpa\Goldsky\Config;

$config = (new Config())
    ->withBaseURL('https://api.goldsky.com/api/v1')
    ->withRetryMaxAttempts(3)
    ->withRetryMutations(false)
    ->withTimeout(60.0)
    ->withMaxResponseBodyBytes(16 * 1024 * 1024);
```

### Laravel configuration

The config file is published with:

```bash
php artisan vendor:publish --tag=goldsky-config
```

Environment variables:

| Variable | Default | Description |
| --- | --- | --- |
| `GOLDSKY_API_KEY` | (empty) | REST project Bearer token |
| `GOLDSKY_EDGE_API_KEY` | (empty) | Edge endpoint API key |
| `GOLDSKY_BASE_URL` | `https://api.goldsky.com/api/v1` | REST base URL |
| `GOLDSKY_EDGE_BASE_URL` | `https://edge.goldsky.com/standard/evm` | Edge RPC base URL |
| `GOLDSKY_RETRY_MAX_ATTEMPTS` | `3` | Total attempts including the first |
| `GOLDSKY_RETRY_MUTATIONS` | `false` | Retry non-idempotent mutations (unsafe) |
| `GOLDSKY_TIMEOUT` | `60` | HTTP timeout in seconds |
| `GOLDSKY_MAX_RESPONSE_BODY_BYTES` | `16777216` | Maximum buffered response size |

## Error handling changes

### Problem classification

Use the `ProblemDetails` classification methods or compare `getType()`:

```php
try {
    $client->pipelines->get('my-pipe');
} catch (ProblemDetails $e) {
    if ($e->isNotFound()) {
        // handle 404
    }
    if ($e->isRateLimited()) {
        [$secs, $ok] = $e->retryAfter();
        if ($ok) sleep($secs);
    }
}
```

### Retry behavior

By default only safe reads (GET, HEAD, OPTIONS) are retried on transport
errors and status codes 429, 500, 502, 503, and 504, using capped exponential
backoff with jitter and honouring `Retry-After`. Mutations are not retried
automatically because Goldsky does not document idempotency keys. Set
`retry_mutations` to opt in to unsafe mutation retry.
