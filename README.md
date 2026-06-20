# Keeal PHP bindings

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)

PHP client for Keeal **hosted checkout**: create sessions with your secret API key, redirect buyers to the returned URL, and verify webhooks.

## Install

```bash
composer require keeal/keeal-php
```

## Quick start (hosted checkout)

```php
<?php

use Keeal\Checkout\KeealCheckout;

$checkout = new KeealCheckout([
    'apiKey' => getenv('KEEAL_API_KEY'),      // keeal_sk_...
    'baseUrl' => 'https://api.keeal.com/api',
]);

$session = $checkout->createSession([
    'line_items' => [[
        'price_data' => [
            'currency' => 'usd',
            'product_data' => ['name' => 'Pro plan'],
            'unit_amount' => 2900,
        ],
        'quantity' => 1,
    ]],
    'success_url' => 'https://yoursite.com/thanks',
    'cancel_url' => 'https://yoursite.com/cart',
    'client_reference_id' => 'order-123',
]);

header('Location: ' . $session['url']);
exit;
```

## Webhooks

```php
<?php

use Keeal\Checkout\WebhookVerifier;

$rawBody = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_KEEAL_SIGNATURE'] ?? '';

$event = WebhookVerifier::constructEvent(
    $rawBody,
    $signature,
    getenv('KEEAL_WEBHOOK_SECRET'),
);

if ($event['type'] === 'checkout.session.completed') {
    $order = $event['data']['object'];
    // $order['client_reference_id'], $order['transaction_id'], ...
}

http_response_code(200);
echo 'ok';
```

## API reference

### `KeealCheckout` (server)

| Method | HTTP |
|--------|------|
| `createSession($params)` | `POST /checkout/sessions` |
| `createSessionUrl($params)` | Same; returns `url` string |
| `listMerchantSessions(['limit' => 20, 'page' => 0])` | `GET /checkout/merchant/sessions` |
| `retrieveMerchantSession($sessionId)` | `GET /checkout/merchant/sessions/:id` |
| `retrieveSession($sessionId)` | Public `GET /checkout/sessions/:id` |
| `createPayment(...)` | **Deprecated** legacy `/pay` |

### Subscription checkout

```php
$checkout->createSession([
    'mode' => 'subscription',
    'subscription_data' => ['price_id' => 'price_abc123'],
    'success_url' => 'https://yoursite.com/welcome',
    'cancel_url' => 'https://yoursite.com/pricing',
]);
```

### `WebhookVerifier`

| Method / constant | Role |
|-------------------|------|
| `WebhookVerifier::SIGNATURE_HEADER` | `X-Keeal-Signature` |
| `WebhookVerifier::EVENT_HEADER` | `X-Keeal-Event` |
| `verify($rawBody, $header, $whsec)` | Returns `bool` |
| `constructEvent($rawBody, $header, $whsec)` | Verify + decode JSON |

## Legacy (backward compatible)

`KeealCheckoutPublic` and `createPayment()` remain for custom `/pay` UIs. New integrations should use hosted checkout only.

## Laravel

See [`../laravel/README.md`](../laravel/README.md) for a ServiceProvider, Facade, and published config.

## Development

```bash
cd php
composer install
composer test
```

## License

**MIT** — see [`LICENSE`](./LICENSE).
