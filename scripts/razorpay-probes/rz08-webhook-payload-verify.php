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

$orderIdFile = __DIR__ . '/state/rz04-last-order-id.txt';
$receiptFile = __DIR__ . '/state/rz04-last-receipt.txt';
if (! is_file($orderIdFile) || ! is_file($receiptFile)) {
    fwrite(STDERR, "FATAL: state/rz04-last-order-id.txt or rz04-last-receipt.txt missing — run rz04 first\n");
    exit(2);
}
$orderId = trim(file_get_contents($orderIdFile));
$receipt = trim(file_get_contents($receiptFile));

$paymentId   = 'pay_rz08_' . bin2hex(random_bytes(4));
$donationId  = 'don-rz04-derived';
$createdAt   = time();
$amountMinor = 50000;

$fixture = file_get_contents(__DIR__ . '/fixtures/webhook-payment-captured.json');
$payloadRaw = strtr($fixture, [
    '__PAYMENT_ID__'  => $paymentId,
    '__ORDER_ID__'    => $orderId,
    '__AMOUNT__'      => (string) $amountMinor,
    '__DONATION_ID__' => $donationId,
    '__RECEIPT__'     => $receipt,
    '__CREATED_AT__'  => (string) $createdAt,
]);

$payloadArr  = json_decode($payloadRaw, true);
$payloadJson = json_encode($payloadArr, JSON_UNESCAPED_SLASHES);

$secret = (string) config('payments.providers.razorpay.webhook_secret');
$header = (string) config('payments.providers.razorpay.webhook_signature_header', 'X-Razorpay-Signature');
$hmac   = hash_hmac('sha256', $payloadJson, $secret);

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayVerificationAdapter;
$adapter = $app->make(RazorpayVerificationAdapter::class);

$result = $adapter->verifyWebhook([$header => $hmac], $payloadJson);

if (! $result->isOk()) {
    respond('rz08', [['name' => 'verifyWebhook returned ok', 'passed' => false]],
        ['error' => $result->error()], (int) ($start * 1000));
}

$verification = $result->value();

$checks = [
    ['name' => 'gateway_order_id matches rz04 order',  'passed' => ($verification['gateway_order_id'] ?? null) === $orderId],
    ['name' => 'gateway_payment_id matches generated', 'passed' => ($verification['gateway_payment_id'] ?? null) === $paymentId],
    ['name' => 'status === CAPTURED',                 'passed' => ($verification['status'] ?? null) === TransactionStatus::CAPTURED],
    ['name' => 'amount === 50000',                    'passed' => (int) ($verification['amount'] ?? 0) === 50000],
    ['name' => 'currency === INR',                    'passed' => ($verification['currency'] ?? null) === 'INR'],
];

respond('rz08', $checks, [
    'paymentId'          => $paymentId,
    'orderId'            => $orderId,
    'gateway_order_id'   => $verification['gateway_order_id'] ?? null,
    'gateway_payment_id' => $verification['gateway_payment_id'] ?? null,
    'status'             => $verification['status']->value ?? null,
    'amount'             => $verification['amount'] ?? null,
    'currency'           => $verification['currency'] ?? null,
    'method'             => $verification['method'] ?? null,
], (int) ($start * 1000));