<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Log\LoggerInterface;
use Tigusigalpa\Goldsky\API\Catalogs;
use Tigusigalpa\Goldsky\API\Edge;
use Tigusigalpa\Goldsky\API\Pipelines;
use Tigusigalpa\Goldsky\API\Subgraphs;
use Tigusigalpa\Goldsky\API\Webhooks;
use Tigusigalpa\Goldsky\Exceptions\GoldskyException;
use Tigusigalpa\Goldsky\GraphQL\GraphQLClient;
use Tigusigalpa\Goldsky\RPC\RPCClient;

/**
 * Client is the top-level Goldsky client. It exposes grouped service clients
 * for the REST control plane and the GraphQL and Edge RPC data planes.
 *
 * Create one client and reuse it for the lifetime of your application. The
 * REST project API token and the Edge endpoint API key are distinct secrets;
 * both are kept private and never appear in error messages or logs.
 */
final class Client
{
    private Requester $requester;

    public readonly Pipelines $pipelines;
    public readonly Subgraphs $subgraphs;
    public readonly Webhooks $webhooks;
    public readonly Edge $edge;
    public readonly Catalogs $catalogs;
    public readonly GraphQLClient $graphQL;
    public readonly RPCClient $rpc;

    public function __construct(string $apiToken, ?Config $config = null, ?GuzzleClient $httpClient = null, ?LoggerInterface $logger = null, ?callable $sleeper = null)
    {
        $apiToken = trim($apiToken);
        if ($apiToken === '') {
            throw new GoldskyException('goldsky: API token is required');
        }
        $config = $config ?? new Config();
        $this->requester = new Requester($apiToken, $config, $httpClient, $logger, $sleeper);

        $this->pipelines = new Pipelines($this->requester);
        $this->subgraphs = new Subgraphs($this->requester);
        $this->webhooks = new Webhooks($this->requester);
        $this->edge = new Edge($this->requester);
        $this->catalogs = new Catalogs($this->requester);
        $this->graphQL = new GraphQLClient($this->requester, $config->graphQLBaseURL, $config->edgeAPIKey);
        $this->rpc = new RPCClient($this->requester, $config->edgeBaseURL, $config->edgeAPIKey);
    }

    public function getRequester(): Requester
    {
        return $this->requester;
    }

    public function getConfig(): Config
    {
        return $this->requester->getConfig();
    }

    public function baseURL(): string
    {
        return $this->requester->getConfig()->baseURL;
    }

    public function userAgent(): string
    {
        return $this->requester->getConfig()->userAgent;
    }

    /**
     * Changes the Edge endpoint API key used by the RPC and GraphQL private
     * helpers. The Edge key is a separate secret from the REST token.
     */
    public function setEdgeAPIKey(string $key): void
    {
        $this->rpc->setEdgeAPIKey($key);
        $this->graphQL->setEdgeAPIKey($key);
    }

    /**
     * Sets the sleeper used for retry delays (injectable for deterministic tests).
     */
    public function setSleeper(callable $sleeper): void
    {
        $this->requester->setSleeper($sleeper);
    }
}
