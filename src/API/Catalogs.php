<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\API;

/**
 * Catalogs lists supported chains, networks, and Edge Data sources. These
 * endpoints are also reachable through the Subgraph and Edge services for
 * convenience; this service groups the catalog reads together.
 *
 * @see https://api.goldsky.com/api/v1/docs#tag/Catalogs
 */
final class Catalogs extends BaseAPI
{
    /**
     * Lists supported deployment chains.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Catalogs/operation/listSubgraphChains
     */
    public function supportedSubgraphChains(): array
    {
        [, $body] = $this->requester->request('GET', ['subgraphs', 'supported-chains']);
        return $this->decode($body);
    }

    /**
     * Lists supported Edge networks.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Catalogs/operation/listEdgeNetworks
     */
    public function edgeNetworks(): array
    {
        [, $body] = $this->requester->request('GET', ['edge', 'networks']);
        return $this->decode($body);
    }

    /**
     * Lists Edge Data sources.
     *
     * @return array<string, mixed>
     *
     * @see https://api.goldsky.com/api/v1/docs#tag/Catalogs/operation/listEdgeSources
     */
    public function edgeSources(): array
    {
        [, $body] = $this->requester->request('GET', ['edge', 'sources']);
        return $this->decode($body);
    }
}
