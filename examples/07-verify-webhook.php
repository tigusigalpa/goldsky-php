<?php

declare(strict_types=1);

/**
 * Example 07: Verify a webhook delivery.
 *
 * Goldsky delivers entity webhook events with the goldsky-webhook-secret
 * header set to the secret you configured at webhook creation time. This
 * example shows how to verify that header in constant time before processing
 * the payload.
 */

require __DIR__ . '/../vendor/autoload.php';

use Tigusigalpa\Goldsky\Webhook\WebhookVerifier;

// In a real application you would read these from the incoming HTTP request.
$expectedSecret = getenv('GOLDSKY_WEBHOOK_SECRET') ?: 'your-webhook-secret';
$providedSecret = getenv('GOLDSKY_WEBHOOK_PROVIDED') ?: '';
$payload = '{"entity":"Transfer","data":[]}';

if ($providedSecret === '') {
    fwrite(STDERR, "Set GOLDSKY_WEBHOOK_PROVIDED to run this example.\n");
    exit(1);
}

if (!WebhookVerifier::verifySecret($providedSecret, $expectedSecret)) {
    http_response_code(401);
    fwrite(STDERR, "Webhook signature verification failed.\n");
    exit(1);
}

// Process the payload
printf("Verified. Payload: %s\n", $payload);
