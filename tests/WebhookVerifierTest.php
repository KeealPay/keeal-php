<?php

declare(strict_types=1);

namespace Keeal\Checkout\Tests;

use Keeal\Checkout\WebhookVerifier;
use PHPUnit\Framework\TestCase;

final class WebhookVerifierTest extends TestCase
{
    public function testVerifyAcceptsValidPayload(): void
    {
        $secret = 'whsec_test_secret';
        $rawBody = '{"id":"evt_1","type":"checkout.session.completed"}';
        $t = (string) time();
        $v1 = hash_hmac('sha256', $t . '.' . $rawBody, $secret, false);
        $header = 't=' . $t . ',v1=' . $v1;

        self::assertTrue(WebhookVerifier::verify($rawBody, $header, $secret, 300));
    }

    public function testVerifyRejectsTamperedBody(): void
    {
        $secret = 'whsec_test_secret';
        $rawBody = '{"id":"evt_1"}';
        $t = (string) time();
        $v1 = hash_hmac('sha256', $t . '.' . $rawBody, $secret, false);
        $header = 't=' . $t . ',v1=' . $v1;

        self::assertFalse(WebhookVerifier::verify($rawBody . ' ', $header, $secret, 300));
    }

    public function testConstructEventReturnsDecodedPayload(): void
    {
        $secret = 'whsec_test_secret';
        $rawBody = '{"id":"evt_1","type":"checkout.session.completed","data":{"object":{}}}';
        $t = (string) time();
        $v1 = hash_hmac('sha256', $t . '.' . $rawBody, $secret, false);
        $header = 't=' . $t . ',v1=' . $v1;

        $event = WebhookVerifier::constructEvent($rawBody, $header, $secret, 300);

        self::assertSame('checkout.session.completed', $event['type']);
    }
}
