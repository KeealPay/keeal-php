<?php

declare(strict_types=1);

namespace Keeal\Checkout;

/**
 * Verifies X-Keeal-Signature (format t=<unix>,v1=<hex>) on the raw request body.
 * Signed payload is "{t}.{rawBody}" HMAC-SHA256 with the whsec secret (raw secret bytes).
 */
final class WebhookVerifier
{
    public const SIGNATURE_HEADER = 'X-Keeal-Signature';

    public const EVENT_HEADER = 'X-Keeal-Event';

    /**
     * @param  string  $rawBody  Exact bytes received (do not re-encode JSON).
     * @param  string  $signatureHeader  Value of X-Keeal-Signature.
     * @param  string  $whsecSigningSecret  Webhook signing secret (whsec_…).
     * @param  int  $toleranceSeconds  Reject stale signatures (default 300). Set 0 to skip.
     */
    public static function verify(
        string $rawBody,
        string $signatureHeader,
        string $whsecSigningSecret,
        int $toleranceSeconds = 300,
    ): bool {
        if ($signatureHeader === '' || $whsecSigningSecret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $eq = strpos($segment, '=');
            if ($eq === false) {
                continue;
            }
            $parts[trim(substr($segment, 0, $eq))] = trim(substr($segment, $eq + 1));
        }
        $t = $parts['t'] ?? null;
        $v1 = $parts['v1'] ?? null;
        if ($t === null || $t === '' || $v1 === null || $v1 === '') {
            return false;
        }

        if ($toleranceSeconds > 0) {
            $ts = (int) $t;
            if ($ts <= 0) {
                return false;
            }
            if (abs(time() - $ts) > $toleranceSeconds) {
                return false;
            }
        }

        $signed = $t . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signed, $whsecSigningSecret, false);
        if (strlen($expected) !== strlen($v1)) {
            return false;
        }

        return hash_equals($expected, $v1);
    }

    /**
     * Verify signature and decode the webhook JSON envelope.
     *
     * @return array<string, mixed>
     *
     * @throws KeealCheckoutException when signature is invalid or JSON cannot be parsed.
     */
    public static function constructEvent(
        string $rawBody,
        string $signatureHeader,
        string $whsecSigningSecret,
        int $toleranceSeconds = 300,
    ): array {
        if (! self::verify($rawBody, $signatureHeader, $whsecSigningSecret, $toleranceSeconds)) {
            throw new KeealCheckoutException('Invalid Keeal webhook signature', 400);
        }

        try {
            $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new KeealCheckoutException('Invalid webhook JSON body', 400);
        }

        if (! is_array($event)) {
            throw new KeealCheckoutException('Invalid webhook JSON body', 400);
        }

        return $event;
    }
}
