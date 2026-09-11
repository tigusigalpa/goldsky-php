<?php

declare(strict_types=1);

/**
 * Example 02: Paginate subgraphs.
 *
 * Usage: GOLDSKY_API_KEY=your-token php 02-paginate-subgraphs.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Tigusigalpa\Goldsky\Client;

$token = getenv('GOLDSKY_API_KEY') ?: '';
if ($token === '') {
    fwrite(STDERR, "Set GOLDSKY_API_KEY to run this example.\n");
    exit(1);
}

$client = new Client($token);

$pager = $client->subgraphs->newPager(['page_size' => 25]);
$total = 0;
for ($i = 0; $i < 100; $i++) {
    $page = $pager->nextPage();
    foreach ($page->data as $subgraph) {
        printf("- %s/%s [%s, %s]\n", $subgraph['name'] ?? '?', $subgraph['version'] ?? '?', $subgraph['status'] ?? '?', $subgraph['health'] ?? '?');
    }
    $total += count($page->data);
    if (!$page->hasMore()) {
        break;
    }
}
printf("Total: %d\n", $total);
