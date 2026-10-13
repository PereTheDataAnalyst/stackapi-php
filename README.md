# StackApi PHP SDK

The official PHP library for [StackApi](https://stackapi.app): payments, payouts, SMS and email, identity checks,
bill payments, virtual cards, logistics, FX rates and more behind one key.

* PHP 8.1 or later with the `curl` and `json` extensions. **No other dependencies.**
* Test keys (`Psk-test-...`) run everything in the sandbox: nothing is sent, booked or charged. Switch to a live key with the same code.

## Install

```bash
composer require stackapi/stackapi-php
```

## First request

```php
<?php
require 'vendor/autoload.php';

use StackApi\StackApi;

$stackapi = new StackApi(getenv('STACKAPI_SECRET_KEY'), getenv('STACKAPI_PUBLIC_KEY'));

$sms = $stackapi->notifications->sendSms(['to' => '08031234567', 'message' => 'Your code is 123456']);
echo $sms['status'];
```

Every method sends the array you give it and returns the API's JSON answer as an array. The fields are the ones in the
[API reference](https://stackapi.app/docs).

| Resource | Methods |
|---|---|
| `notifications` | `sendSms`, `sendBulkSms`, `smsStatus`, `sendOtp`, `verifyOtp`, `sendEmail`, `createEmailDomain`, `listEmailDomains`, `getEmailDomain`, `verifyEmailDomain`, `setEmailDomainReceiving`, `removeEmailDomain`, `simulateInbound`, `listInbound`, `getInbound` |
| `payments` | `initiate`, `get` |
| `identity` | `verifyBvn`, `verifyNin`, `verifyPhone`, `verifyCac`, `verifyBankAccount`, `companySearch`, `verify($check, $body)`, `pricing` |
| `addressVerifications` | `create`, `list`, `get`, `simulateComplete` |
| `vtu` | `airtime`, `data`, `plans`, `electricity`, `cableTv`, `betting`, `education`, `verifyCustomer`, `transaction` |
| `payouts` | `banks`, `fees`, `resolve`, `create`, `get`, `list` |
| `banking` | `createAccount`, `listAccounts`, `getAccount`, `payments`, `simulatePayment`, `banks`, `resolve` |
| `fx` | `rates`, `convert`, `history`, `currencies` |
| `cards` | `createCardholder`, `listCardholders`, `getCardholder`, `create`, `list`, `get`, `fund`, `withdraw`, `freeze`, `unfreeze`, `terminate`, `transactions`, `reveal`, `simulateTransaction` |
| `logistics` | `createAddress`, `listAddresses`, `getAddress`, `categories`, `createQuote`, `createShipment`, `listShipments`, `getShipment`, `cancelShipment`, `simulateShipment` |

Anything else your key can call: `$stackapi->request('POST', '/v1/commerce/invoices', null, [...])`.

## Errors

A non-2xx answer throws `StackApi\StackApiException` with `$e->status`, `$e->errorCode` (StackApi's error code, for
example `INSUFFICIENT_BALANCE`) and `$e->getMessage()`. A request that never reached StackApi throws with status 0 and
code `NETWORK_ERROR`.

Reads (GET) are retried on network errors, 429 and 502/503/504. Writes are never retried by the SDK: send your own
`reference` where the endpoint takes one and repeat the call; the API answers the first result again.

## Webhooks

```php
use StackApi\Webhook;
use StackApi\WebhookVerificationException;

try {
    $event = Webhook::verify(file_get_contents('php://input'), getallheaders(), getenv('STACKAPI_WEBHOOK_SECRET'));
} catch (WebhookVerificationException $e) {
    http_response_code(400);
    exit;
}
```

`Webhook::verify` checks `X-StackApi-Signature` (`v1=` HMAC-SHA256 of `"<timestamp>.<raw body>"`) with `hash_equals`
and refuses a timestamp more than 5 minutes away.

## Options

`new StackApi($secretKey, $publicKey, ['base_url' => ..., 'timeout' => 30, 'max_retries' => 2])`

## Tests

`composer test` (no framework needed).

## License

MIT
