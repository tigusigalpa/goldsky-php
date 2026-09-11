<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\RPC;

use Tigusigalpa\Goldsky\Exceptions\GoldskyException;
use Tigusigalpa\Goldsky\Exceptions\ProblemDetails;
use Tigusigalpa\Goldsky\Exceptions\TransportException;
use Tigusigalpa\Goldsky\Requester;

/**
 * RPCClient calls the Edge HTTPS JSON-RPC data plane.
 *
 * Edge endpoint credentials are sent in X-ERPC-Secret-Token rather than the
 * URL, keeping the secret out of access logs, browser history, and metrics.
 */
final class RPCClient
{
    public const EDGE_SECRET_HEADER = 'X-ERPC-Secret-Token';

    private int $idCounter = 0;

    public function __construct(
        private readonly Requester $requester,
        private string $baseURL,
        private string $edgeAPIKey,
    ) {
        $this->edgeAPIKey = trim($this->edgeAPIKey);
    }

    public function setEdgeAPIKey(string $key): void
    {
        $this->edgeAPIKey = trim($key);
    }

    /**
     * Builds the Edge RPC URL for a chain ID. Authentication is sent in a
     * request header, so the returned URL never contains the Edge secret.
     */
    public function endpointURL(int $chainID): string
    {
        return rtrim($this->baseURL, '/') . '/' . $chainID;
    }

    /**
     * Performs a single JSON-RPC call. When $result is provided (by reference),
     * the decoded result is written into it. A JSON-RPC error is thrown as a
     * GoldskyException; malformed envelopes are TransportExceptions.
     *
     * @param mixed $params
     * @param mixed $result Reference to fill with the decoded result (or null).
     */
    public function call(int $chainID, string $method, $params = null, &$result = null): void
    {
        $this->validateCall($chainID, $method);
        $id = $this->nextID();
        $response = $this->post($chainID, [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => $id,
        ]);
        $this->validateResponse($response, $id, 'rpc');

        if (array_key_exists('error', $response)) {
            $this->throwRPCError($response['error']);
        }
        $result = $response['result'];
    }

    /**
     * Performs a JSON-RPC batch call. Responses are matched to calls by index.
     * Missing response IDs are returned as per-call JSON-RPC errors; duplicate,
     * unknown, or malformed response IDs are rejected as transport failures.
     *
     * @param array<int, array{method: string, params?: mixed, result?: mixed}> $calls
     *
     * @return array<int, array{jsonrpc: string, id: int, result?: mixed, error?: array{code: int, message: string, data?: mixed}}>
     */
    public function batch(int $chainID, array &$calls): array
    {
        $this->requireEdgeAPIKey();
        if ($calls === []) {
            return [];
        }
        if ($chainID <= 0) {
            throw new GoldskyException("goldsky rpc: chain ID must be positive, got {$chainID}");
        }

        $requests = [];
        foreach ($calls as $index => $call) {
            $method = $call['method'] ?? null;
            if (!is_string($method) || trim($method) === '') {
                throw new GoldskyException("goldsky rpc: method is required for batch call {$index}");
            }
            $requests[] = [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $call['params'] ?? null,
                'id' => $this->nextID(),
            ];
        }

        $responses = $this->postBatch($chainID, $requests);
        $expectedIDs = array_fill_keys(array_column($requests, 'id'), true);
        $byID = [];
        foreach ($responses as $response) {
            if (!is_array($response) || !array_key_exists('id', $response) || !is_int($response['id'])) {
                throw new TransportException('rpc batch', 0, 'response has an invalid JSON-RPC id');
            }
            $id = $response['id'];
            if (!isset($expectedIDs[$id])) {
                throw new TransportException('rpc batch', 0, "unexpected JSON-RPC response id {$id}");
            }
            if (isset($byID[$id])) {
                throw new TransportException('rpc batch', 0, "duplicate JSON-RPC response id {$id}");
            }
            $this->validateResponse($response, $id, 'rpc batch');
            $byID[$id] = $response;
        }

        $out = [];
        foreach ($calls as $index => $call) {
            $id = $requests[$index]['id'];
            $response = $byID[$id] ?? [
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32603, 'message' => 'no response for request id'],
            ];
            $out[$index] = $response;
            if (array_key_exists('result', $response) && array_key_exists('result', $call)) {
                $calls[$index]['result'] = $response['result'];
            }
        }

        return $out;
    }

    private function validateCall(int $chainID, string $method): void
    {
        $this->requireEdgeAPIKey();
        if ($chainID <= 0) {
            throw new GoldskyException("goldsky rpc: chain ID must be positive, got {$chainID}");
        }
        if (trim($method) === '') {
            throw new GoldskyException('goldsky rpc: method is required');
        }
    }

    private function requireEdgeAPIKey(): void
    {
        if ($this->edgeAPIKey === '') {
            throw new GoldskyException('goldsky rpc: Edge API key is required; set it with the edge API key option or setEdgeAPIKey()');
        }
    }

    private function nextID(): int
    {
        return ++$this->idCounter;
    }

    /** @return array<string, mixed> */
    private function post(int $chainID, array $request): array
    {
        [$status, $body] = $this->send($chainID, $request);
        try {
            $trimmed = trim($body);
            if ($trimmed === '' || $trimmed[0] !== '{') {
                throw new \JsonException('expected a JSON object');
            }
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransportException('rpc', $status, 'decode response: ' . $e->getMessage(), $e);
        }
        return $decoded;
    }

    /** @return array<int, array<string, mixed>> */
    private function postBatch(int $chainID, array $requests): array
    {
        [$status, $body] = $this->send($chainID, $requests);
        try {
            $trimmed = trim($body);
            if ($trimmed === '' || $trimmed[0] !== '[') {
                throw new \JsonException('expected a JSON array');
            }
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new TransportException('rpc batch', $status, 'decode response: ' . $e->getMessage(), $e);
        }
        return $decoded;
    }

    /** @return array{0: int, 1: string} */
    private function send(int $chainID, array $payload): array
    {
        $options = [
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => $this->requester->getConfig()->userAgent,
                self::EDGE_SECRET_HEADER => $this->edgeAPIKey,
            ],
            'json' => $payload,
        ];
        [$status, $body] = $this->requester->rawRequest('POST', $this->endpointURL($chainID), $options, false);
        if ($status < 200 || $status >= 300) {
            throw new ProblemDetails(type: 'about:blank', status: $status, detail: 'Edge RPC endpoint returned non-2xx status', rawBody: $body);
        }
        return [$status, $body];
    }

    /** @param array<string, mixed> $response */
    private function validateResponse(array $response, int $expectedID, string $operation): void
    {
        if (($response['jsonrpc'] ?? null) !== '2.0') {
            throw new TransportException($operation, 0, 'invalid JSON-RPC version');
        }
        if (($response['id'] ?? null) !== $expectedID) {
            throw new TransportException($operation, 0, "response id does not match request id {$expectedID}");
        }
        $hasResult = array_key_exists('result', $response);
        $hasError = array_key_exists('error', $response);
        if ($hasResult === $hasError) {
            throw new TransportException($operation, 0, 'JSON-RPC response must contain exactly one of result or error');
        }
        if ($hasError && (!is_array($response['error'])
            || !isset($response['error']['code'])
            || !is_int($response['error']['code'])
            || !isset($response['error']['message'])
            || !is_string($response['error']['message']))) {
            throw new TransportException($operation, 0, 'invalid JSON-RPC error object');
        }
    }

    /** @param array{code: int, message: string, data?: mixed} $error */
    private function throwRPCError(array $error): void
    {
        throw new GoldskyException(
            'goldsky rpc error ' . $error['code'] . ': ' . $error['message'],
            $error['code'],
        );
    }
}
