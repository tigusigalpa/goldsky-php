<?php

declare(strict_types=1);

/**
 * Example 06: Call the Edge JSON-RPC endpoint.
 *
 * Usage: GOLDSKY_EDGE_API_KEY=your-edge-key php 06-edge-rpc.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Tigusigalpa\Goldsky\Client;
use Tigusigalpa\Goldsky\Config;

$token = getenv('GOLDSKY_API_KEY') ?: 'unused';
$edgeKey = getenv('GOLDSKY_EDGE_API_KEY') ?: '';
if ($edgeKey === '') {
    fwrite(STDERR, "Set GOLDSKY_EDGE_API_KEY to run this example.\n");
    exit(1);
}

$config = (new Config())->withEdgeAPIKey($edgeKey);
$client = new Client($token, $config);

// Single call: eth_blockNumber on Ethereum mainnet (chain ID 1)
$blockNumber = null;
$client->rpc->call(1, 'eth_blockNumber', null, $blockNumber);
printf("Latest block: %s\n", $blockNumber);

// Batch call: eth_blockNumber + eth_chainId
$calls = [
    ['method' => 'eth_blockNumber'],
    ['method' => 'eth_chainId'],
];
$responses = $client->rpc->batch(1, $calls);
foreach ($responses as $i => $r) {
    printf("  batch[%d] result: %s\n", $i, $r['result'] ?? ($r['error']['message'] ?? 'error'));
}
