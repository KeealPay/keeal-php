<?php

declare(strict_types=1);

namespace Keeal\Checkout\Tests;

use Keeal\Checkout\KeealCheckout;
use PHPUnit\Framework\TestCase;

final class KeealCheckoutTest extends TestCase
{
    public function testCreateSessionPostsPaymentModeBody(): void
    {
        $fake = new FakeHttpTransport([
            ['status' => 200, 'body' => '{"id":"cs_pay","url":"https://pay.keeal.test/cs_pay"}'],
        ]);

        $client = new KeealCheckout([
            'apiKey' => 'keeal_sk_test',
            'baseUrl' => 'https://api.keeal.test/api/',
            'http' => $fake,
        ]);

        $params = [
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => ['name' => 'Widget'],
                        'unit_amount' => 1500,
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => 'https://shop.test/thanks',
            'cancel_url' => 'https://shop.test/cart',
        ];

        $result = $client->createSession($params, ['idempotencyKey' => 'idem-fixture']);

        self::assertSame('cs_pay', $result['id']);
        self::assertCount(1, $fake->requests);
        self::assertSame('POST', $fake->requests[0]['method']);
        self::assertSame('https://api.keeal.test/api/checkout/sessions', $fake->requests[0]['url']);

        $headers = implode("\n", $fake->requests[0]['headers']);
        self::assertStringContainsString('Authorization: Bearer keeal_sk_test', $headers);
        self::assertStringContainsString('Idempotency-Key: idem-fixture', $headers);

        $body = json_decode($fake->requests[0]['body'] ?? '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($params, $body);
    }

    public function testCreateSessionPostsSubscriptionModeWithSubscriptionData(): void
    {
        $fake = new FakeHttpTransport([
            ['status' => 200, 'body' => '{"id":"cs_sub","url":"https://pay.keeal.test/cs_sub"}'],
        ]);

        $client = new KeealCheckout([
            'apiKey' => 'keeal_sk_test',
            'baseUrl' => 'https://api.keeal.test/api',
            'http' => $fake,
        ]);

        $params = [
            'mode' => 'subscription',
            'subscription_data' => [
                'price_id' => 'price_catalog_abc',
                'auto_charge_enabled' => true,
            ],
            'success_url' => 'https://shop.test/welcome',
            'cancel_url' => 'https://shop.test/pricing',
        ];

        $client->createSession($params);

        $body = json_decode($fake->requests[0]['body'] ?? '', true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('subscription', $body['mode']);
        self::assertSame('price_catalog_abc', $body['subscription_data']['price_id']);
        self::assertTrue($body['subscription_data']['auto_charge_enabled']);
    }

    public function testRetrieveSessionSkipsAuthorization(): void
    {
        $fake = new FakeHttpTransport([
            ['status' => 200, 'body' => '{"sessionId":"cs_public","status":"open"}'],
        ]);

        $client = new KeealCheckout([
            'apiKey' => 'keeal_sk_test',
            'baseUrl' => 'https://api.keeal.test/api',
            'http' => $fake,
        ]);

        $session = $client->retrieveSession('cs_public');

        self::assertSame('cs_public', $session['sessionId']);
        self::assertSame('GET', $fake->requests[0]['method']);
        self::assertStringContainsString('/checkout/sessions/cs_public', $fake->requests[0]['url']);

        $headers = implode("\n", $fake->requests[0]['headers']);
        self::assertStringNotContainsString('Authorization:', $headers);
    }

    public function testCreatePaymentStillPostsLegacyPayEndpoint(): void
    {
        $fake = new FakeHttpTransport([
            ['status' => 200, 'body' => '{"paymentId":"pay_1","clientSecret":"pi_secret"}'],
        ]);

        $client = new KeealCheckout([
            'apiKey' => 'keeal_sk_test',
            'baseUrl' => 'https://api.keeal.test/api',
            'http' => $fake,
        ]);

        $result = $client->createPayment('cs_legacy', ['amountCents' => 500]);

        self::assertSame('pay_1', $result['paymentId']);
        self::assertStringContainsString('/checkout/sessions/cs_legacy/pay', $fake->requests[0]['url']);

        $headers = implode("\n", $fake->requests[0]['headers']);
        self::assertStringNotContainsString('Authorization:', $headers);
        self::assertStringContainsString('Idempotency-Key:', $headers);
    }
}
