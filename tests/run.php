<?php
// No network and no test framework: a fake transport records each request. Run: php tests/run.php (or composer test)
declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') as $f) {
    require_once $f;
}

use StackApi\StackApi;
use StackApi\StackApiException;
use StackApi\NetworkException;
use StackApi\Webhook;
use StackApi\WebhookVerificationException;

$pass = 0;
$fail = 0;
function check(string $name, bool $ok, $detail = ''): void
{
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? '  PASS  ' : '  FAIL  ') . $name . ($ok ? '' : '   <- ' . json_encode($detail)) . PHP_EOL;
}
function fake(array $answers): object
{
    return new class($answers) {
        public array $calls = [];
        public function __construct(public array $answers) {}
        public function __invoke(string $m, string $url, array $h, ?string $body, int $t): array
        {
            $this->calls[] = [$m, $url, $h, $body];
            $a = array_shift($this->answers) ?? [200, [], '{}'];
            if ($a instanceof \Throwable) {
                throw $a;
            }
            return $a;
        }
    };
}
$opts = fn ($f, $extra = []) => ['base_url' => 'https://api.example.test/', 'transport' => $f] + $extra;

$f = fake([[200, [], json_encode(['id' => 'x', 'status' => 'sent'])]]);
$s = new StackApi('Psk-test-abc', 'Ppk-test-def', $opts($f));
$r = $s->notifications->sendSms(['to' => '08031234567', 'message' => 'hi']);
[$m, $url, $h, $body] = $f->calls[0];
check('sends both keys, JSON and the path', $r['status'] === 'sent' && $m === 'POST' && $url === 'https://api.example.test/v1/notifications/sms'
    && $h['X-API-Key'] === 'Psk-test-abc' && $h['X-Public-Key'] === 'Ppk-test-def' && json_decode($body, true) === ['to' => '08031234567', 'message' => 'hi'], $f->calls[0]);
check('a test key is a test key', $s->isTest());

$f = fake([[200, [], '{}'], [200, [], '{}'], [200, [], '{}'], [200, [], '{}']]);
$s = new StackApi('Psk-test-abc', 'Ppk-test-def', $opts($f));
$s->fx->convert('USD', 'NGN', '10.50');
$s->payouts->fees();
$s->cards->fund('ref/1 2', 100);
$s->addressVerifications->simulateComplete('abc');
check('query strings skip empty values', $f->calls[0][1] === 'https://api.example.test/v1/fx/convert?from=USD&to=NGN&amount=10.50'
    && $f->calls[1][1] === 'https://api.example.test/v1/payouts/fees', [$f->calls[0][1], $f->calls[1][1]]);
check('path parameters are encoded', $f->calls[2][1] === 'https://api.example.test/v1/cards/ref%2F1%202/fund' && $f->calls[2][3] === '{"amount":"100"}', $f->calls[2]);
check('an empty body is a JSON object', $f->calls[3][3] === '{}', $f->calls[3][3]);

$f = fake([[402, [], json_encode(['success' => false, 'code' => 'INSUFFICIENT_BALANCE', 'error' => 'Top up'])]]);
try {
    (new StackApi('Psk-test-abc', 'Ppk-test-def', $opts($f)))->payouts->create(['amount' => '1']);
    check('an error answer throws', false);
} catch (StackApiException $e) {
    check('an error answer throws StackApiException with the code', $e->status === 402 && $e->errorCode === 'INSUFFICIENT_BALANCE' && $e->getMessage() === 'Top up');
}

$f = fake([[503, ['Retry-After' => '0'], '{}'], [200, [], '{"ok":1}']]);
$r = (new StackApi('Psk-test-abc', 'Ppk-test-def', $opts($f)))->fx->currencies();
check('reads are retried on 503', $r === ['ok' => 1] && count($f->calls) === 2);
$f = fake([[503, [], '{"code":"X"}'], [200, [], '{}']]);
try {
    (new StackApi('Psk-test-abc', 'Ppk-test-def', $opts($f)))->notifications->sendSms(['to' => '1', 'message' => 'x']);
} catch (StackApiException $e) {
}
check('writes are sent once', count($f->calls) === 1);
$f = fake([new NetworkException('down'), [200, [], '{}']]);
try {
    (new StackApi('Psk-test-abc', 'Ppk-test-def', $opts($f)))->cards->terminate('c1');
    check('a network failure throws', false);
} catch (StackApiException $e) {
    check('a network failure on a write is reported, not retried', $e->status === 0 && $e->errorCode === 'NETWORK_ERROR' && count($f->calls) === 1);
}
try {
    new StackApi('', 'x');
    check('keys are required', false);
} catch (\InvalidArgumentException $e) {
    check('keys are required', true);
}

// the server's test vector (src/services/webhook_retry.rs, signature_tests): change one, change all
check('webhook signatures: the shared vector', hash_hmac('sha256', '1700000000.{"event":"payment.success","data":{"id":1}}', 'whsec_test')
    === '365712a702dbb9edc33c9debd1d94609a670ab648b8925bf8cc8cc98dca63fbb');
$secret = 'whsec_abc';
$t = time();
$body = json_encode(['event' => 'card.transaction', 'data' => ['id' => 'x']]);
$sig = 'v1=' . hash_hmac('sha256', $t . '.' . $body, $secret);
$ev = Webhook::verify($body, ['X-StackApi-Signature' => $sig, 'X-StackApi-Timestamp' => (string) $t], $secret);
check('a good webhook is accepted', $ev['event'] === 'card.transaction');
$bad = 0;
foreach ([[$body . ' ', $secret, $t, $sig], [$body, 'other', $t, $sig]] as [$b, $sec, $tt, $sg]) {
    try {
        Webhook::verify($b, ['x-stackapi-signature' => $sg, 'x-stackapi-timestamp' => (string) $tt], $sec);
    } catch (WebhookVerificationException $e) {
        $bad++;
    }
}
$old = $t - 600;
try {
    Webhook::verify($body, ['x-stackapi-signature' => 'v1=' . hash_hmac('sha256', $old . '.' . $body, $secret), 'x-stackapi-timestamp' => (string) $old], $secret);
} catch (WebhookVerificationException $e) {
    $bad += str_contains($e->getMessage(), 'too old') ? 1 : 0;
}
check('a changed body, a wrong secret and an old timestamp are refused', $bad === 3, $bad);

echo PHP_EOL . "$pass passed, $fail failed" . PHP_EOL;
exit($fail ? 1 : 0);
