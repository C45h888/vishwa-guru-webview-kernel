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
if (! is_file($orderIdFile)) {
    fwrite(STDERR, "FATAL: state/rz04-last-order-id.txt missing — run rz04 first\n");
    exit(2);
}
$orderId = trim(file_get_contents($orderIdFile));

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayAdapter;

$adapter = null;
foreach ($app->tagged('payment_gateway') as $gw) {
    if ($gw instanceof RazorpayAdapter) { $adapter = $gw; break; }
}
if (! $adapter) {
    respond('rz05', [['name' => 'RazorpayAdapter in pool', 'passed' => false]],
        ['error' => 'RazorpayAdapter not registered'], (int) ($start * 1000));
}

$result = $adapter->verify($orderId);

if (! $result->isOk()) {
    respond('rz05', [['name' => 'verify returned ok', 'passed' => false]],
        ['error' => $result->error(), 'orderId' => $orderId], (int) ($start * 1000));
}

$status = $result->value();

$checks = [
    ['name' => 'status is INITIALIZED',  'passed' => $status === TransactionStatus::INITIALIZED],
    ['name' => 'status is not terminal', 'passed' => ! $status->isTerminal()],
];

respond('rz05', $checks, [
    'orderId'     => $orderId,
    'status'      => $status->value,
    'statusLabel' => $status->label(),
    'isTerminal'  => $status->isTerminal(),
], (int) ($start * 1000));