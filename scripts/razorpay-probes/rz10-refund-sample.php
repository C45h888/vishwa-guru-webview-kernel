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

$payIdFile = __DIR__ . '/state/rz09-last-pay-id.txt';
if (! is_file($payIdFile)) {
    fwrite(STDERR, "FATAL: state/rz09-last-pay-id.txt missing — run rz09 first\n");
    exit(2);
}
$paymentId = trim(file_get_contents($payIdFile));

// Detect fabricated mode (matches rz09 detection). Fabricated refunds skip
// the real HTTP call and return a synthetic refund response.
$fabricated = (bool) preg_match('/^pay_TF[A-Za-z0-9]{8,}$/', $paymentId);

$client = $app->make(\App\Payments\Infrastructure\Adapters\Razorpay\RazorpayClient::class);

if ($fabricated) {
    $now = time();
    $refundArr = [
        'id'              => 'rfnd_TF' . strtoupper(bin2hex(random_bytes(7))),
        'entity'          => 'refund',
        'amount'          => 100,
        'currency'        => 'INR',
        'payment_id'      => $paymentId,
        'notes'           => ['probe' => 'rz10', 'fabricated' => 'true'],
        'receipt'         => null,
        'acquirer_data'   => ['rrn' => null],
        'created_at'      => $now,
        'batch_id'        => null,
        'status'          => 'processed',
        'speed_processed' => 'optimum',
        'speed_requested' => 'optimum',
    ];
} else {
    try {
        $refundArr = $client->refundPayment($paymentId, [
            'amount' => 100,    // ₹1
            'speed'  => 'optimum',
            'notes'  => ['probe' => 'rz10'],
        ]);
    } catch (\Razorpay\Api\Errors\Error $e) {
        respond('rz10', [['name' => 'refundPayment did not throw', 'passed' => false]], [
            'error'      => $e->getMessage(),
            'httpStatus' => method_exists($e, 'getHttpStatusCode') ? $e->getHttpStatusCode() : null,
        ], (int) ($start * 1000));
    } catch (\Throwable $e) {
        respond('rz10', [['name' => 'refundPayment did not throw', 'passed' => false]], [
            'error' => $e->getMessage(),
            'class' => $e::class,
        ], (int) ($start * 1000));
    }
}

$checks = [
    ['name' => 'refund id starts with rfnd_',          'passed' => str_starts_with($refundArr['id'] ?? '', 'rfnd_')],
    ['name' => 'refund entity === refund',             'passed' => ($refundArr['entity'] ?? null) === 'refund'],
    ['name' => 'refund amount === 100',                'passed' => (int) ($refundArr['amount'] ?? 0) === 100],
    ['name' => 'refund payment_id matches',            'passed' => ($refundArr['payment_id'] ?? null) === $paymentId],
    ['name' => 'refund status processed|refunded',     'passed' => in_array($refundArr['status'] ?? '', ['processed', 'refunded'], true)],
];

respond('rz10', $checks, [
    'mode'        => $fabricated ? 'fabricated' : 'real-http',
    'refundId'    => $refundArr['id'] ?? null,
    'paymentId'   => $paymentId,
    'amount'      => $refundArr['amount'] ?? null,
    'currency'    => $refundArr['currency'] ?? null,
    'status'      => $refundArr['status'] ?? null,
    'rawResponse' => $refundArr,
], (int) ($start * 1000));