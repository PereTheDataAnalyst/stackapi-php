<?php
// Runs a few real calls with a TEST key (nothing is sent or charged). Used by sdks/integration_test.py:
//   STACKAPI_SECRET_KEY=Psk-test-... STACKAPI_PUBLIC_KEY=Ppk-test-... php examples/smoke.php
declare(strict_types=1);
foreach (glob(__DIR__ . '/../src/*.php') as $f) {
    require_once $f;
}
use StackApi\StackApi;
use StackApi\StackApiException;

$s = new StackApi(getenv('STACKAPI_SECRET_KEY'), getenv('STACKAPI_PUBLIC_KEY'), ['base_url' => getenv('STACKAPI_BASE_URL') ?: StackApi::DEFAULT_BASE_URL]);
if (!$s->isTest()) {
    fwrite(STDERR, "use a test key\n");
    exit(1);
}
$out = [];
$out['sms'] = $s->notifications->sendSms(['to' => '08031234567', 'message' => 'Hello from the PHP SDK'])['status'];
$out['fx'] = $s->fx->convert('USD', 'NGN', '10')['data']['result'];
$out['banks'] = count($s->payouts->banks()['data']);
$h = $s->cards->createCardholder(['first_name' => 'Ada', 'last_name' => 'Obi', 'phone' => '08031234567', 'dob' => '1990-04-01',
    'address' => ['line1' => '12 Admiralty Way', 'city' => 'Lekki', 'state' => 'Lagos'], 'id_type' => 'BVN', 'id_number' => '22222222222']);
$card = $s->cards->create(['cardholder_id' => $h['data']['id'], 'currency' => 'NGN']);
$s->cards->fund($card['data']['id'], '5000');
$s->cards->simulateTransaction($card['data']['id'], ['amount' => '1200', 'merchant' => 'Jumia']);
$out['card_balance'] = $s->cards->get($card['data']['id'])['data']['balance'];
$out['address'] = $s->logistics->createAddress(['name' => 'Ada', 'email' => 'ada@example.com', 'phone' => '08031234567', 'address' => '12 Admiralty Way, Lekki'])['data']['city'];
try {
    $s->payouts->create(['amount' => '1', 'bank_code' => '001', 'account_number' => '0123456789']);
    $out['error'] = 'none';
} catch (StackApiException $e) {
    $out['error'] = $e->status . ' ' . $e->errorCode;
}
echo json_encode($out) . PHP_EOL;
