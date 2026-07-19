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

$client = $app->make(\App\Payments\Infrastructure\Adapters\Razorpay\RazorpayClient::class);

$checks = [
    ['name' => 'RazorpayClient resolves from container',  'passed' => $client instanceof \App\Payments\Infrastructure\Adapters\Razorpay\RazorpayClient],
    ['name' => 'client has createOrder method',           'passed' => method_exists($client, 'createOrder')],
    ['name' => 'client has fetchOrder method',            'passed' => method_exists($client, 'fetchOrder')],
    ['name' => 'client has fetchPayment method',          'passed' => method_exists($client, 'fetchPayment')],
    ['name' => 'client has refundPayment method',         'passed' => method_exists($client, 'refundPayment')],
    ['name' => 'client has verifyWebhookSignature method','passed' => method_exists($client, 'verifyWebhookSignature')],
];

respond('rz02', $checks, [
    'clientClass'     => $client::class,
    'keyIdFromConfig' => (string) config('payments.providers.razorpay.key_id'),
], (int) ($start * 1000));