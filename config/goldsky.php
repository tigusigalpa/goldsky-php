<?php

declare(strict_types=1);

/**
 * Goldsky SDK configuration.
 *
 * Publish to your Laravel app with:
 *   php artisan vendor:publish --tag=goldsky-config
 *
 * All values should be provided via environment variables; never commit
 * real credentials to source control.
 */

use Tigusigalpa\Goldsky\Config;

return [
    // REST project API token (Bearer). Required. Scoped to one project.
    'api_key' => env('GOLDSKY_API_KEY', ''),

    // Edge endpoint API key. Separate secret from api_key. Sent in the
    // X-ERPC-Secret-Token header. Obtain from Edge::create() or Edge::revealKey().
    'edge_api_key' => env('GOLDSKY_EDGE_API_KEY', ''),

    // REST control-plane base URL.
    'base_url' => env('GOLDSKY_BASE_URL', Config::DEFAULT_BASE_URL),

    // Edge RPC base URL.
    'edge_base_url' => env('GOLDSKY_EDGE_BASE_URL', Config::DEFAULT_EDGE_BASE_URL),

    // Subgraph GraphQL data-plane base URL.
    'graphql_base_url' => env('GOLDSKY_GRAPHQL_BASE_URL', Config::DEFAULT_GRAPHQL_BASE_URL),

    // User-Agent header.
    'user_agent' => env('GOLDSKY_USER_AGENT', Config::DEFAULT_USER_AGENT),

    // Retry policy: total attempts (including the first). 1 disables retry.
    'retry_max_attempts' => (int) env('GOLDSKY_RETRY_MAX_ATTEMPTS', 3),

    // Retry mutations (non-idempotent). Unsafe; off by default.
    'retry_mutations' => filter_var(env('GOLDSKY_RETRY_MUTATIONS', false), FILTER_VALIDATE_BOOL),

    // HTTP timeout in seconds.
    'timeout' => (float) env('GOLDSKY_TIMEOUT', 60.0),

    // TLS verification. Disable only for local testing.
    'verify_tls' => filter_var(env('GOLDSKY_VERIFY_TLS', true), FILTER_VALIDATE_BOOL),

    // Largest REST, GraphQL, or Edge RPC response buffered in memory (16 MiB).
    'max_response_body_bytes' => (int) env('GOLDSKY_MAX_RESPONSE_BODY_BYTES', Config::DEFAULT_MAX_RESPONSE_BODY_BYTES),
];
