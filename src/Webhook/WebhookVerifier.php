<?php

declare(strict_types=1);

namespace Tigusigalpa\Goldsky\Webhook;

/**
 * WebhookVerifier verifies the goldsky-webhook-secret header on webhook
 * deliveries in constant time.
 *
 * Goldsky deliveries carry the secret verbatim in the goldsky-webhook-secret
 * header; Goldsky does not document an HMAC signature format, so this helper
 * performs a direct constant-time comparison only. An empty expected secret
 * is always rejected to prevent accidental acceptance of unset secrets.
 */
final class WebhookVerifier
{
    public const SECRET_HEADER = 'goldsky-webhook-secret';

    public static function verifySecret(string $provided, string $expected): bool
    {
        if ($expected === '') {
            return false;
        }
        return hash_equals($expected, $provided);
    }

    /**
     * @param array<string, string|string[]> $headers
     */
    public static function verifyRequest(array $headers, string $expected): bool
    {
        $value = $headers[self::SECRET_HEADER] ?? $headers[strtolower(self::SECRET_HEADER)] ?? '';
        if (is_array($value)) {
            $value = $value[0] ?? '';
        }
        return self::verifySecret((string) $value, $expected);
    }
}
