<?php

declare(strict_types=1);

/**
 * Example 03: Validate a pipeline definition.
 *
 * Usage: GOLDSKY_API_KEY=your-token php 03-validate-pipeline.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Tigusigalpa\Goldsky\Client;

$token = getenv('GOLDSKY_API_KEY') ?: '';
if ($token === '') {
    fwrite(STDERR, "Set GOLDSKY_API_KEY to run this example.\n");
    exit(1);
}

$client = new Client($token);

$result = $client->pipelines->validate([
    'name' => 'my-pipeline',
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

printf("Valid: %s\n", $result['valid'] ? 'true' : 'false');
foreach ($result['errors'] ?? [] as $err) {
    printf("  ERROR: %s — %s\n", $err['field'] ?? '(no field)', $err['message']);
}
foreach ($result['warnings'] ?? [] as $warn) {
    printf("  WARN:  %s — %s\n", $warn['field'] ?? '(no field)', $warn['message']);
}
