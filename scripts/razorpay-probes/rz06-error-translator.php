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

use App\Payments\Infrastructure\Adapters\Common\GatewayErrorTranslator;
use App\Payments\Domain\Exceptions\PaymentInitializationFailedException;

$cases = [];

// Case 1: idempotency substring → PaymentInitializationFailedException
$ex1 = new \RuntimeException('An idempotency conflict occurred for the supplied key');
$translated1 = GatewayErrorTranslator::forInitialization('razorpay', $ex1);
$cases[] = [
    'name'         => 'idempotency conflict',
    'inputMessage' => $ex1->getMessage(),
    'outputClass'  => $translated1::class,
];

// Case 2: currency substring → PaymentInitializationFailedException (gatewayRejected)
$ex2 = new \RuntimeException('Currency USD is not supported for this account');
$translated2 = GatewayErrorTranslator::forInitialization('razorpay', $ex2);
$cases[] = [
    'name'         => 'invalid currency',
    'inputMessage' => $ex2->getMessage(),
    'outputClass'  => $translated2::class,
];

// Case 3: amount substring → PaymentInitializationFailedException (gatewayRejected)
$ex3 = new \RuntimeException('Amount 100 is below the minimum allowed');
$translated3 = GatewayErrorTranslator::forInitialization('razorpay', $ex3);
$cases[] = [
    'name'         => 'invalid amount',
    'inputMessage' => $ex3->getMessage(),
    'outputClass'  => $translated3::class,
];

// Case 4: generic SDK failure → PaymentInitializationFailedException (sdkFailure)
$ex4 = new \RuntimeException('Connection timeout to gateway');
$translated4 = GatewayErrorTranslator::forInitialization('razorpay', $ex4);
$cases[] = [
    'name'         => 'gateway unavailable',
    'inputMessage' => $ex4->getMessage(),
    'outputClass'  => $translated4::class,
];

$checks = array_map(fn($c) => [
    'name'   => $c['name'],
    'passed' => ($c['outputClass'] ?? null) === PaymentInitializationFailedException::class,
], $cases);

respond('rz06', $checks, ['cases' => $cases], (int) ($start * 1000));