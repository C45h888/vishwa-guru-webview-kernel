<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

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

$keyId      = (string) config('payments.providers.razorpay.key_id', '');
$keySecret  = (string) config('payments.providers.razorpay.key_secret', '');
$webhookSec = (string) config('payments.providers.razorpay.webhook_secret', '');

$checks = [
    ['name' => 'RAZORPAY_KEY_ID starts with rzp_test_',  'passed' => str_starts_with($keyId, 'rzp_test_')],
    ['name' => 'RAZORPAY_KEY_SECRET non-empty',         'passed' => strlen($keySecret) > 0],
    ['name' => 'RAZORPAY_WEBHOOK_SECRET non-empty',     'passed' => strlen($webhookSec) > 0],
];

respond('rz01', $checks, [
    'keyIdPrefix'      => substr($keyId, 0, 9),
    'keySecretLen'     => strlen($keySecret),
    'webhookSecretLen' => strlen($webhookSec),
], (int) ($start * 1000));