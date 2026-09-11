<?php

declare(strict_types=1);

/**
 * Example 01: List pipelines.
 *
 * Usage: GOLDSKY_API_KEY=your-token php 01-list-pipelines.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Tigusigalpa\Goldsky\Client;

$token = getenv('GOLDSKY_API_KEY') ?: '';
if ($token === '') {
    fwrite(STDERR, "Set GOLDSKY_API_KEY to run this example.\n");
    exit(1);
}

$client = new Client($token);

$page = $client->pipelines->list(['page_size' => 50]);
foreach ($page->data as $pipeline) {
    printf("- %s [%s]\n", $pipeline['name'] ?? '?', $pipeline['status'] ?? '?');
}
printf("Has more: %s\n", $page->hasMore() ? 'yes' : 'no');
