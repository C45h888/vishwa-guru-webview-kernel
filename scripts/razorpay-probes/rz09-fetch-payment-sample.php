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

// ─── Parse sample-payment-id.txt ────────────────────────────────────────────
$sampleIdFile = __DIR__ . '/fixtures/sample-payment-id.txt';
$rawLines = file($sampleIdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$sampleId = '';
foreach ($rawLines as $line) {
    if (! str_starts_with(trim($line), '#')) { $sampleId = trim($line); break; }
}

if ($sampleId === '' || ! str_starts_with($sampleId, 'pay_')) {
    respond('rz09', [['name' => 'sample-payment-id.txt contains valid pay_* ID', 'passed' => false]],
        ['error' => 'fixtures/sample-payment-id.txt missing valid ID'], (int) ($start * 1000));
}

// Detect fabricated mode: Razorpay test-mode IDs match `pay_TF<14 hex chars>`
// (or `pay_TEST` prefix from older samples). Fabricated IDs skip the real
// HTTP call and return a synthetic payment shaped like a real Razorpay
// response. This lets the harness run end-to-end before any captured payment
// exists in the Razorpay test-mode dashboard.
$fabricated = (bool) preg_match('/^pay_TF[A-Za-z0-9]{8,}$/', $sampleId);

$client = $app->make(\App\Payments\Infrastructure\Adapters\Razorpay\RazorpayClient::class);

if ($fabricated) {
    // Build a synthetic payment that matches the shape of Razorpay's response.
    $now = time();
    $payment = [
        'id'              => $sampleId,
        'entity'          => 'payment',
        'amount'          => 50000,
        'currency'        => 'INR',
        'status'          => 'captured',
        'order_id'        => null,
        'invoice_id'      => null,
        'international'   => false,
        'method'          => 'card',
        'amount_refunded' => 0,
        'refund_status'   => null,
        'captured'        => true,
        'description'     => 'rz09-fabricated-sample',
        'card_id'         => null,
        'bank'            => null,
        'wallet'          => null,
        'vpa'             => null,
        'email'           => 'donor@example.in',
        'contact'         => '+919999999999',
        'notes'           => ['fabricated' => 'true', 'probe' => 'rz09'],
        'fee'             => 1180,
        'tax'             => 180,
        'error_code'      => null,
        'error_description' => null,
        'created_at'      => $now,
    ];
} else {
    try {
        $payment = $client->fetchPayment($sampleId);
    } catch (\Razorpay\Api\Errors\Error $e) {
        respond('rz09', [['name' => 'fetchPayment did not throw SDK error', 'passed' => false]], [
            'error'       => $e->getMessage(),
            'httpStatus'  => method_exists($e, 'getHttpStatusCode') ? $e->getHttpStatusCode() : null,
            'sampleId'    => $sampleId,
            'remediation' => 'Sample payment ID retired — update fixtures/sample-payment-id.txt from https://dashboard.razorpay.com/app/payments (Test Mode)',
        ], (int) ($start * 1000));
    } catch (\Throwable $e) {
        respond('rz09', [['name' => 'fetchPayment did not throw', 'passed' => false]], [
            'error' => $e->getMessage(),
            'class' => $e::class,
        ], (int) ($start * 1000));
    }
}

$checks = [
    ['name' => 'payment id matches sample',      'passed' => ($payment['id'] ?? null) === $sampleId],
    ['name' => 'payment has entity === payment', 'passed' => ($payment['entity'] ?? null) === 'payment'],
    ['name' => 'payment has amount > 0',         'passed' => (int) ($payment['amount'] ?? 0) > 0],
    ['name' => 'payment has currency INR',       'passed' => ($payment['currency'] ?? null) === 'INR'],
    ['name' => 'payment has status captured',    'passed' => ($payment['status'] ?? null) === 'captured'],
];

// Persist for rz10, rz11.
$stateDir = __DIR__ . '/state';
if (! is_dir($stateDir)) { mkdir($stateDir, 0755, true); }
file_put_contents($stateDir . '/rz09-last-pay-id.txt', $sampleId);

respond('rz09', $checks, [
    'sampleId'    => $sampleId,
    'mode'        => $fabricated ? 'fabricated' : 'real-http',
    'paymentId'   => $payment['id'] ?? null,
    'amount'      => $payment['amount'] ?? null,
    'currency'    => $payment['currency'] ?? null,
    'status'      => $payment['status'] ?? null,
    'method'      => $payment['method'] ?? null,
], (int) ($start * 1000));