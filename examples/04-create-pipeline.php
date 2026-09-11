<?php

declare(strict_types=1);

/**
 * Example 04: Create a pipeline.
 *
 * Usage: GOLDSKY_API_KEY=your-token php 04-create-pipeline.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Tigusigalpa\Goldsky\Client;

$token = getenv('GOLDSKY_API_KEY') ?: '';
if ($token === '') {
    fwrite(STDERR, "Set GOLDSKY_API_KEY to run this example.\n");
    exit(1);
}

$client = new Client($token);

$pipeline = $client->pipelines->create([
    'name' => 'my-pipeline',
    'resource_size' => 'small',
    'definition' => [
        'sources' => [
            'my-source' => [
                'type' => 'webhook',
                'options' => ['url' => 'https://example.com/webhook'],
            ],
        ],
        'transforms' => [],
        'sinks' => [
            'my-sink' => [
                'type' => 'webhook',
                'options' => ['url' => 'https://example.com/sink'],
            ],
        ],
    ],
]);

printf("Created: %s [%s]\n", $pipeline['name'], $pipeline['status']);
