<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Tigusigalpa\Goldsky\API\Pipelines pipelines()
 * @method static \Tigusigalpa\Goldsky\API\Subgraphs subgraphs()
 * @method static \Tigusigalpa\Goldsky\API\Webhooks webhooks()
 * @method static \Tigusigalpa\Goldsky\API\Edge edge()
 * @method static \Tigusigalpa\Goldsky\API\Catalogs catalogs()
 * @method static \Tigusigalpa\Goldsky\GraphQL\GraphQLClient graphQL()
 * @method static \Tigusigalpa\Goldsky\RPC\RPCClient rpc()
 * @method static void setEdgeAPIKey(string $key)
 *
 * @see \Tigusigalpa\Goldsky\Client
 */
final class Goldsky extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'goldsky';
    }
}
