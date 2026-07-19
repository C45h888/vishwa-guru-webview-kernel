<?php

declare(strict_types=1);

$app = require __DIR__ . '/_bootstrap.php';
$start = microtime(true);

function respond(string $probe, array $checks, array $evidence, int $startMs): never
{
    $passed = array_reduce($checks, fn($c, $i) => $c && $i['passed'], true);
    $out = [
        'probe'      => $probe,
        'status'     => $passed ? 'pass' : 'fail',
        'durationMs' => (int) ((microtime(true) * 1000) - $startMs),
        'checks'     => $checks,
        'evidence'   => $evidence,
    ];
    echo json_encode($out, JSON_UNESCAPED_SLASHES) . "\n";
    exit($passed ? 0 : 1);
}

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayAdapter;
use App\Shared\ValueObjects\Identifier;

// Resolve the adapter via the tagged pool (mirrors runtime path).
$adapter = null;
foreach ($app->tagged('payment_gateway') as $gw) {
    if ($gw instanceof RazorpayAdapter) { $adapter = $gw; break; }
}
if (! $adapter) {
    respond('rz04', [['name' => 'RazorpayAdapter in payment_gateway pool', 'passed' => false]],
        ['error' => 'RazorpayAdapter not registered in payment_gateway tag'], (int) ($start * 1000));
}

$idempotencyKey = 'rz04-' . bin2hex(random_bytes(6));
$donorId        = 'donor-rz04-' . bin2hex(random_bytes(4));
$amountMinor    = 50000; // ₹500

$request = new PaymentRequest(
    donorIdentifier: new Identifier($donorId),
    amount: $amountMinor,
    currency: Currency::INR,
    purpose: 'rz04-probe-donation',
    metadata: ['probe' => 'rz04'],
    idempotencyKey: $idempotencyKey,
);

$result = $adapter->initialize($request);

if (! $result->isOk()) {
    respond('rz04', [['name' => 'initialize returned ok', 'passed' => false]],
        ['error' => $result->error()], (int) ($start * 1000));
}

$response = $result->value();
$gatewayOrderId = $response->gatewayOrderId();

$checks = [
    ['name' => 'gatewayOrderId starts with order_',  'passed' => str_starts_with($gatewayOrderId, 'order_')],
    ['name' => 'amount === 50000',                   'passed' => $response->amountMinor() === 50000],
    ['name' => 'currency === INR',                   'passed' => $response->currency() === Currency::INR],
    ['name' => 'providerCode === razorpay',          'passed' => $response->providerCode() === 'razorpay'],
    ['name' => 'rawStatusString === created',        'passed' => $response->rawStatusString() === 'created'],
];

// Persist state for rz05, rz08, rz11.
$stateDir = __DIR__ . '/state';
if (! is_dir($stateDir)) { mkdir($stateDir, 0755, true); }
file_put_contents($stateDir . '/rz04-last-order-id.txt', $gatewayOrderId);
file_put_contents($stateDir . '/rz04-last-receipt.txt', $idempotencyKey);
file_put_contents($stateDir . '/rz04-last-donor-id.txt', $donorId);

respond('rz04', $checks, [
    'gatewayOrderId' => $gatewayOrderId,
    'amountMinor'    => $response->amountMinor(),
    'currency'       => $response->currency()->value,
    'providerCode'   => $response->providerCode(),
    'rawResponse'    => $response->rawResponse(),
], (int) ($start * 1000));