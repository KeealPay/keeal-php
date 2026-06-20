<?php

declare(strict_types=1);

namespace Keeal\Checkout\Tests;

use Keeal\Checkout\KeealCheckout;
use PHPUnit\Framework\TestCase;

/**
 * Optional live API tests. Run with:
 * KEEAL_E2E=1 KEEAL_API_KEY=keeal_sk_… KEEAL_BASE_URL=https://api.staging.keeal.com/api composer test -- --filter KeealCheckoutE2eTest
 */
final class KeealCheckoutE2eTest extends TestCase
{
    private static function enabled(): bool
    {
        return getenv('KEEAL_E2E') === '1'
            && is_string(getenv('KEEAL_API_KEY')) && getenv('KEEAL_API_KEY') !== ''
            && is_string(getenv('KEEAL_BASE_URL')) && getenv('KEEAL_BASE_URL') !== '';
    }

    public function testCreatePaymentSessionAgainstLiveApi(): void
    {
        if (!self::enabled()) {
            self::markTestSkipped('Set KEEAL_E2E=1, KEEAL_API_KEY, and KEEAL_BASE_URL to run live API tests.');
        }

        $client = new KeealCheckout([
            'apiKey' => (string) getenv('KEEAL_API_KEY'),
            'baseUrl' => (string) getenv('KEEAL_BASE_URL'),
        ]);

        $result = $client->createSession([
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => ['name' => 'PHP SDK E2E payment'],
                        'unit_amount' => 100,
                    ],
                    'quantity' => 1,
                ],
            ],
            'success_url' => 'https://example.com/success',
            'cancel_url' => 'https://example.com/cancel',
            'client_reference_id' => 'php-sdk-e2e-' . time(),
        ]);

        self::assertArrayHasKey('id', $result);
        self::assertStringStartsWith('cs_', $result['id']);
        self::assertStringContainsString($result['id'], $result['url']);
    }

    public function testCreateSubscriptionSessionWhenPriceIdConfigured(): void
    {
        if (!self::enabled()) {
            self::markTestSkipped('Set KEEAL_E2E=1, KEEAL_API_KEY, and KEEAL_BASE_URL to run live API tests.');
        }

        $priceId = getenv('KEEAL_E2E_PRICE_ID');
        if (!is_string($priceId) || $priceId === '') {
            self::markTestSkipped('Set KEEAL_E2E_PRICE_ID to run subscription E2E.');
        }

        $client = new KeealCheckout([
            'apiKey' => (string) getenv('KEEAL_API_KEY'),
            'baseUrl' => (string) getenv('KEEAL_BASE_URL'),
        ]);

        $result = $client->createSession([
            'mode' => 'subscription',
            'subscription_data' => ['price_id' => $priceId],
            'success_url' => 'https://example.com/welcome',
            'cancel_url' => 'https://example.com/pricing',
            'client_reference_id' => 'php-sdk-e2e-sub-' . time(),
        ]);

        self::assertStringStartsWith('cs_', $result['id']);
        self::assertStringContainsString($result['id'], $result['url']);
    }
}
