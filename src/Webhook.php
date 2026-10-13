<?php
declare(strict_types=1);

namespace StackApi;

/** Webhook verification: X-StackApi-Signature is v1=<hex HMAC-SHA256 of "<timestamp>.<raw body>">. */
final class Webhook
{
    /**
     * Verifies a webhook from StackApi and returns the parsed event (['event' => ..., 'data' => ..., ...]).
     * $rawBody must be the body exactly as received (file_get_contents('php://input')), before any JSON parsing.
     *
     * @param array<string, string|string[]> $headers for example getallheaders()
     * @throws WebhookVerificationException
     */
    public static function verify(string $rawBody, array $headers, string $secret, int $tolerance = 300): array
    {
        if ($secret === '') {
            throw new WebhookVerificationException('No webhook secret given.');
        }
        $signature = self::header($headers, 'x-stackapi-signature');
        $ts = self::header($headers, 'x-stackapi-timestamp');
        if ($signature === null || $ts === null) {
            throw new WebhookVerificationException('The X-StackApi-Signature and X-StackApi-Timestamp headers are missing.');
        }
        if (!ctype_digit(trim($ts))) {
            throw new WebhookVerificationException('The timestamp is not a number.');
        }
        $t = (int) trim($ts);
        if (abs(time() - $t) > $tolerance) {
            throw new WebhookVerificationException('The timestamp is too old or in the future.');
        }
        $expected = 'v1=' . hash_hmac('sha256', $t . '.' . $rawBody, $secret);
        if (!hash_equals($expected, trim($signature))) {
            throw new WebhookVerificationException('The signature does not match.');
        }
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            throw new WebhookVerificationException('The body is not JSON.');
        }
        return $event;
    }

    /** @param array<string, string|string[]> $headers */
    private static function header(array $headers, string $name): ?string
    {
        foreach ($headers as $k => $v) {
            if (strtolower((string) $k) === $name) {
                return is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
            }
        }
        $server = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$server]) ? (string) $_SERVER[$server] : null;
    }
}
