<?php

declare(strict_types=1);

/**
 * Example 08: Handle API errors.
 *
 * Shows how to catch and classify RFC 9457 problem responses and transport
 * errors.
 *
 * Usage: GOLDSKY_API_KEY=your-token php 08-handle-errors.php
 */

require __DIR__ . '/../vendor/autoload.php';

use Tigusigalpa\Goldsky\Client;
use Tigusigalpa\Goldsky\Exceptions\ProblemDetails;
use Tigusigalpa\Goldsky\Exceptions\TransportException;

$token = getenv('GOLDSKY_API_KEY') ?: '';
if ($token === '') {
    fwrite(STDERR, "Set GOLDSKY_API_KEY to run this example.\n");
    exit(1);
}

$client = new Client($token);

try {
    $client->pipelines->get('nonexistent-pipeline');
} catch (ProblemDetails $e) {
    if ($e->isNotFound()) {
        printf("Pipeline not found (type=%s)\n", $e->getType());
    } elseif ($e->isRateLimited()) {
        [$secs, $ok] = $e->retryAfter();
        printf("Rate limited, retry after %d seconds\n", $secs);
    } elseif ($e->isValidation()) {
        foreach ($e->getErrors() as $err) {
            printf("Validation error: %s — %s\n", $err['field'] ?? '(no field)', $err['message']);
        }
    } else {
        printf("API error %d: %s\n", $e->getStatus(), $e->getMessage());
    }
} catch (TransportException $e) {
    printf("Transport error: %s\n", $e->getMessage());
}
