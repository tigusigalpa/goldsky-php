<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\RPC;

use GuzzleHttp\Exception\GuzzleException;
use Tigusigalpa\Goldsky\Exceptions\GoldskyException;
use Tigusigalpa\Goldsky\Exceptions\ProblemDetails;
use Tigusigalpa\Goldsky\Exceptions\TransportException;
use Tigusigalpa\Goldsky\Requester;

/**
 * RPCClient calls the Edge HTTPS JSON-RPC data plane.
 *
 * The Edge endpoint URL is
 *   https://edge.goldsky.com/standard/evm/{chainId}?key={edgeAPIKey}
 * The Edge API key is a separate secret from the REST project Bearer token and
 * is carried in the query string; it is never logged or included in error
 * messages. Goldsky documents HTTPS only; there is no WebSocket/subscription
 * support.
 */
final class RPCClient
{
    private int $idCounter = 0;

    public function __construct(
        private readonly Requester $requester,
        private string $baseURL,
        private string $edgeAPIKey,
    ) {
    }

    public function setEdgeAPIKey(string $key): void
    {
        $this->edgeAPIKey = $key;
    }

    public function endpointURL(int $chainID): string
    {
        return $this->baseURL . '/' . $chainID . '?key=' . rawurlencode($this->edgeAPIKey);
    }

    /**
     * Performs a single JSON-RPC call. When $result is provided (by reference),
     * the decoded result is written into it. A non-null RPCError is thrown
     * when the server reports a JSON-RPC error.
     *
     * @param mixed $params
     * @param mixed $result Reference to fill with the decoded result (or null).
     *
     * @throws GoldskyException On a JSON-RPC error.
     */
    public function call(int $chainID, string $method, $params = null, &$result = null): void
    {
        if ($this->edgeAPIKey === '') {
            throw new GoldskyException('goldsky rpc: Edge API key is required; set it with the edge API key option or setEdgeAPIKey()');
        }
        $req = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $this->nextID(),
        ];
        $resp = $this->post($chainID, $req);
        if (isset($resp['error'])) {
            throw new GoldskyException(
                'goldsky rpc error ' . ($resp['error']['code'] ?? -1) . ': ' . ($resp['error']['message'] ?? 'unknown'),
                (int) ($resp['error']['code'] ?? -1),
            );
        }
        if (array_key_exists('result', $resp)) {
            $result = $resp['result'];
        }
    }

    /**
     * Performs a JSON-RPC batch call. Responses are matched to calls by index.
     *
     * @param array<int, array{method: string, params?: mixed, result?: mixed}> $calls
     *
     * @return array<int, array{jsonrpc: string, id: int, result?: mixed, error?: array{code: int, message: string, data?: mixed}}>
     */
    public function batch(int $chainID, array &$calls): array
    {
        if ($this->edgeAPIKey === '') {
            throw new GoldskyException('goldsky rpc: Edge API key is required; set it with the edge API key option or setEdgeAPIKey()');
        }
        if (empty($calls)) {
            return [];
        }
        $reqs = [];
        foreach ($calls as $c) {
            $reqs[] = [
                'jsonrpc' => '2.0',
                'method' => $c['method'],
                'params' => $c['params'] ?? null,
                'id' => $this->nextID(),
            ];
        }
        $resps = $this->postBatch($chainID, $reqs);
        $byID = [];
        foreach ($resps as $r) {
            $byID[$r['id']] = $r;
        }
        $out = [];
        foreach ($calls as $i => $c) {
            $id = $reqs[$i]['id'];
            $r = $byID[$id] ?? ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => -32603, 'message' => 'no response for request id']];
            $out[$i] = $r;
            if (array_key_exists('result', $r) && !isset($r['error']) && array_key_exists('result', $c)) {
                $calls[$i]['result'] = $r['result'];
            }
        }
        return $out;
    }

    private function nextID(): int
    {
        return ++$this->idCounter;
    }

    /**
     * @return array<string, mixed>
     */
    private function post(int $chainID, array $req): array
    {
        $options = [
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $this->requester->getConfig()->userAgent,
            ],
            'json' => $req,
        ];
        try {
            [$status, $body] = $this->requester->rawRequest('POST', $this->endpointURL($chainID), $options, false);
        } catch (GuzzleException $e) {
            throw new TransportException('rpc', 0, $e->getMessage(), $e);
        }
        if ($status < 200 || $status >= 300) {
            throw new ProblemDetails(type: 'about:blank', status: $status, detail: 'Edge RPC endpoint returned non-2xx status', rawBody: $body);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new TransportException('rpc', $status, 'decode rpc response: invalid JSON');
        }
        return $decoded;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function postBatch(int $chainID, array $reqs): array
    {
        $options = [
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $this->requester->getConfig()->userAgent,
            ],
            'json' => $reqs,
        ];
        try {
            [$status, $body] = $this->requester->rawRequest('POST', $this->endpointURL($chainID), $options, false);
        } catch (GuzzleException $e) {
            throw new TransportException('rpc', 0, $e->getMessage(), $e);
        }
        if ($status < 200 || $status >= 300) {
            throw new ProblemDetails(type: 'about:blank', status: $status, detail: 'Edge RPC endpoint returned non-2xx status', rawBody: $body);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new TransportException('rpc', $status, 'decode rpc batch response: invalid JSON');
        }
        return $decoded;
    }
}
