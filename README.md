# Keeal PHP SDK

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)

Official **PHP** client for Keeal **hosted checkout**: create sessions with your secret API key, redirect buyers to the returned URL, and verify webhooks.

**Repository:** [github.com/KeealPay/keeal-php](https://github.com/KeealPay/keeal-php) · **Package:** `keeal/keeal-php`

---

## Overview

Keeal **hosted checkout** is a redirect-based payment flow:

1. **Create a session** on your server with your secret API key (`keeal_sk_…`).
2. **Redirect** the customer to the session `url` returned by the API.
3. **Fulfill** your order when you receive a signed `checkout.session.completed` webhook.

Your server never handles card data. Payment UI, PayPal, and cancellation are handled on Keeal's hosted page.

---

## Installation

```bash
composer require keeal/keeal-php
```

Requires **PHP 8.1+** with the `json` extension.

---

## Configuration

Set these environment variables on your server:

| Variable | Description |
|----------|-------------|
| `KEEAL_API_KEY` | Secret API key (`keeal_sk_…`) from the Keeal dashboard |
| `KEEAL_BASE_URL` | API base URL including `/api`, e.g. `https://api.keeal.com/api` |
| `KEEAL_WEBHOOK_SECRET` | Webhook signing secret (`whsec_…`) from **Settings → API Keys → Webhook** |

Use separate keys, base URLs, and webhook secrets for staging and production.

When constructing `KeealCheckout`, pass `apiKey` and `baseUrl` explicitly (from `getenv()` or your framework's config).

---

## Quick start

```php
<?php

use Keeal\Checkout\KeealCheckout;

$checkout = new KeealCheckout([
    'apiKey' => getenv('KEEAL_API_KEY'),
    'baseUrl' => getenv('KEEAL_BASE_URL'),
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

---

## API reference

### `KeealCheckout`

Server-side client. Requires your secret API key.

| Method | Signature | Description | Status |
|--------|-----------|-------------|--------|
| `__construct` | `__construct(array $options)` | `apiKey` required. `baseUrl` required (defaults to `https://api.keeal.com/api` when omitted). Optional: `defaultHeaders`, `http`. | Hosted |
| `createSession` | `createSession(array $params, array $options = [])` → `array{id, url}` | Create a checkout session. Sends `Idempotency-Key` (auto-generated if omitted). | Hosted |
| `createSessionUrl` | `createSessionUrl(array $params, array $options = [])` → `string` | Convenience wrapper; returns the hosted checkout `url`. | Hosted |
| `listMerchantSessions` | `listMerchantSessions(?array $options = null)` → `array` | List your checkout sessions. Options: `limit`, `page`. | Hosted |
| `retrieveMerchantSession` | `retrieveMerchantSession(string $sessionId)` → `array` | Get one session by `cs_…` id, including `payments[]`. | Hosted |
| `retrieveSession` | `retrieveSession(string $sessionId)` → `array` | Public session lookup (no API key sent). | Hosted |
| `createPayment` | `createPayment(string $sessionId, array $params, array $options = [])` → `array` | Legacy custom `/pay` flow. | **Deprecated** |
| `cancelSession` | `cancelSession(string $sessionId)` → `void` | Legacy session cancel. | **Deprecated** |
| `abandonSession` | `abandonSession(string $sessionId)` → `void` | Legacy session abandon. | **Deprecated** |
| `paypalCreateOrder` | `paypalCreateOrder(string $sessionId, array $params)` → `array` | Legacy PayPal create-order. | **Deprecated** |
| `paypalCapture` | `paypalCapture(string $sessionId, array $params)` → `array` | Legacy PayPal capture. | **Deprecated** |

### `KeealCheckoutPublic`

Unauthenticated client for legacy custom checkout UIs. **Not recommended for new integrations.**

| Method | Signature | Description | Status |
|--------|-----------|-------------|--------|
| `__construct` | `__construct(array $options)` | `baseUrl` required. Optional: `defaultHeaders`, `http`. | **Deprecated** |
| `retrieveSession` | `retrieveSession(string $sessionId)` → `array` | Public session lookup. | **Deprecated** |
| `createPayment` | `createPayment(string $sessionId, array $params, array $options = [])` → `array` | Legacy `/pay` from a custom UI. | **Deprecated** |
| `cancelSession` | `cancelSession(string $sessionId)` → `void` | Legacy cancel. | **Deprecated** |
| `abandonSession` | `abandonSession(string $sessionId)` → `void` | Legacy abandon. | **Deprecated** |
| `paypalCreateOrder` | `paypalCreateOrder(string $sessionId, array $params)` → `array` | Legacy PayPal. | **Deprecated** |
| `paypalCapture` | `paypalCapture(string $sessionId, array $params)` → `array` | Legacy PayPal. | **Deprecated** |

### `WebhookVerifier`

| Method / constant | Signature | Description |
|-------------------|-----------|-------------|
| `SIGNATURE_HEADER` | — | `X-Keeal-Signature` |
| `EVENT_HEADER` | — | `X-Keeal-Event` |
| `verify` | `verify(string $rawBody, string $signatureHeader, string $whsec, int $toleranceSeconds = 300)` → `bool` | Verify signature on the **raw** request body. |
| `constructEvent` | `constructEvent(string $rawBody, string $signatureHeader, string $whsec, int $toleranceSeconds = 300)` → `array` | Verify signature and decode the JSON envelope. Throws `KeealCheckoutException` on failure. |

---

## Webhook verification

Configure your webhook URL in the Keeal dashboard. Verify signatures on the **raw** request body — do not re-encode JSON.

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

Signature format: `t=<unix_seconds>,v1=<hex_hmac>` where HMAC-SHA256 is computed over `<t>.<rawBody>` using your `whsec_…` secret.

---

## Subscription checkout

Pass `mode => 'subscription'` when creating a session:

```php
$checkout->createSession([
    'mode' => 'subscription',
    'subscription_data' => ['price_id' => 'price_abc123'],
    'success_url' => 'https://yoursite.com/welcome',
    'cancel_url' => 'https://yoursite.com/pricing',
]);
```

Subscription lifecycle events (`subscription.created`, `subscription.activated`, etc.) are delivered to the same webhook URL and verified with the same signing secret as checkout events.

---

## Legacy & deprecated APIs

These classes and methods remain for backward compatibility but are **not offered to new merchants**:

| Symbol | Use instead |
|--------|-------------|
| `KeealCheckoutPublic` | `KeealCheckout::createSession()` + redirect to `url` |
| `KeealCheckout::createPayment()` | Redirect to session `url` |
| `KeealCheckoutPublic::createPayment()` | Redirect to session `url` |
| `cancelSession` / `abandonSession` | Handled on Keeal's hosted pay page |
| `paypalCreateOrder` / `paypalCapture` | Handled on Keeal's hosted pay page |

---

## Laravel

Using Laravel? See [`keeal/laravel-checkout`](https://github.com/KeealPay/keeal-laravel) for a ServiceProvider, Facade, config publishing, and webhook middleware.

---

## Development

```bash
composer install
composer test
```

### End-to-end tests (optional)

```bash
export KEEAL_E2E=1
export KEEAL_API_KEY=keeal_sk_test_…
export KEEAL_BASE_URL=http://localhost:8000/api
export KEEAL_E2E_PRICE_ID=price_…   # optional subscription E2E
composer test -- --filter KeealCheckoutE2eTest
```

---

## License

**MIT** — see [`LICENSE`](./LICENSE).
