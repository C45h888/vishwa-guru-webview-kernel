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

$adapter = $app->make(\App\Payments\Infrastructure\Adapters\Razorpay\RazorpayProviderAdapter::class);

$currencies = $adapter->supportedCurrencies();

$checks = [
    ['name' => 'name is razorpay',                      'passed' => $adapter->name() === 'razorpay'],
    ['name' => 'supportedCurrencies includes INR',      'passed' => in_array(\App\Payments\Domain\Enums\Currency::INR, $currencies, true)],
    ['name' => 'supportedCurrencies count === 1',       'passed' => count($currencies) === 1],
    ['name' => 'minimumAmount === 100 (₹1)',            'passed' => $adapter->minimumAmount() === 100],
    ['name' => 'maximumAmount === 99_999_999 (~₹1cr)',  'passed' => $adapter->maximumAmount() === 99999999],
    ['name' => 'priority === 10',                       'passed' => $adapter->priority() === 10],
    ['name' => 'isEnabled returns true',                'passed' => $adapter->isEnabled() === true],
];

respond('rz03', $checks, [
    'name'           => $adapter->name(),
    'displayName'    => $adapter->displayName(),
    'currencies'     => array_map(fn($c) => $c->value, $currencies),
    'minimumAmount'  => $adapter->minimumAmount(),
    'maximumAmount'  => $adapter->maximumAmount(),
    'priority'       => $adapter->priority(),
    'isEnabled'      => $adapter->isEnabled(),
], (int) ($start * 1000));