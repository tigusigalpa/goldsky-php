<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Laravel;

use Illuminate\Support\ServiceProvider;
use Tigusigalpa\Goldsky\Client;
use Tigusigalpa\Goldsky\Config;

/**
 * Laravel service provider for the Goldsky SDK.
 *
 * Publish the config with:
 *   php artisan vendor:publish --tag=goldsky-config
 */
final class GoldskyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/goldsky.php', 'goldsky');

        $this->app->singleton(Client::class, function ($app) {
            $config = new Config();
            $config->withBaseURL((string) config('goldsky.base_url', Config::DEFAULT_BASE_URL))
                ->withUserAgent((string) config('goldsky.user_agent', Config::DEFAULT_USER_AGENT))
                ->withEdgeAPIKey((string) config('goldsky.edge_api_key', ''))
                ->withEdgeBaseURL((string) config('goldsky.edge_base_url', Config::DEFAULT_EDGE_BASE_URL))
                ->withGraphQLBaseURL((string) config('goldsky.graphql_base_url', Config::DEFAULT_GRAPHQL_BASE_URL))
                ->withRetryMaxAttempts((int) config('goldsky.retry_max_attempts', 3))
                ->withRetryMutations((bool) config('goldsky.retry_mutations', false))
                ->withTimeout((float) config('goldsky.timeout', 60.0))
                ->withVerifyTls((bool) config('goldsky.verify_tls', true));

            return new Client(
                apiToken: (string) config('goldsky.api_key', ''),
                config: $config,
            );
        });

        $this->app->alias(Client::class, 'goldsky');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/goldsky.php' => config_path('goldsky.php'),
            ], 'goldsky-config');
        }
    }
}
