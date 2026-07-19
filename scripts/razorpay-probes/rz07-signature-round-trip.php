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

use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayVerificationAdapter;

$adapter = $app->make(RazorpayVerificationAdapter::class);
$secret  = (string) config('payments.providers.razorpay.webhook_secret');

$payload  = '{"event":"payment.captured","payload":{"payment":{"entity":{"id":"pay_X"}}}}';
$expected = hash_hmac('sha256', $payload, $secret);

// generateSignature (1 arg, reads secret from config)
$generated = $adapter->generateSignature($payload);

// verifySignature (2 args, reads secret from config)
$validTrue  = $adapter->verifySignature($payload, $expected);

// verifySignature rejects tampered payload
$tampered   = str_replace('pay_X', 'pay_TAMPERED', $payload);
$validFalse = $adapter->verifySignature($tampered, $expected);

$checks = [
    ['name' => 'generateSignature matches hash_hmac',       'passed' => hash_equals($expected, $generated)],
    ['name' => 'verifySignature accepts correct signature', 'passed' => $validTrue === true],
    ['name' => 'verifySignature rejects tampered payload',  'passed' => $validFalse === false],
];

respond('rz07', $checks, [
    'secretLen'         => strlen($secret),
    'generatedSigLen'   => strlen($generated),
    'verifyTrueResult'  => $validTrue,
    'verifyFalseResult' => $validFalse,
], (int) ($start * 1000));