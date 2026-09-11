<?php

declare(strict_types=1);

/**
 * Example 05: Query a Subgraph GraphQL endpoint.
 *
 * Usage: GOLDSKY_API_KEY=your-token PROJECT_ID=your-project-id php 05-graphql-query.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Tigusigalpa\Goldsky\Client;

$token = getenv('GOLDSKY_API_KEY') ?: '';
$projectID = getenv('PROJECT_ID') ?: '';
if ($token === '' || $projectID === '') {
    fwrite(STDERR, "Set GOLDSKY_API_KEY and PROJECT_ID to run this example.\n");
    exit(1);
}

$client = new Client($token);

$response = $client->graphQL->queryPrivate($projectID, 'my-subgraph', 'latest', [
    'query' => '{ _meta { block { number } } }',
]);

if ($client->graphQL->hasErrors($response)) {
    foreach ($response['errors'] as $err) {
        fwrite(STDERR, "GraphQL error: " . $err['message'] . "\n");
    }
    exit(1);
}

printf("Block number: %s\n", json_encode($response['data'] ?? null));
