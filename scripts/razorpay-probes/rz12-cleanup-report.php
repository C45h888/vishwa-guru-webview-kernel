<?php

declare(strict_types=1);

require __DIR__ . '/_bootstrap.php';

$stateDir = __DIR__ . '/state';

$report = [
    'probe'                => 'rz12',
    'status'               => 'pass',
    'durationMs'           => 0,
    'createdDuringRun'     => [
        'orders'   => [],
        'payments' => [],
        'refunds'  => [],
    ],
    'cleanupInstructions'  => 'Log into https://dashboard.razorpay.com (Test Mode) and manually delete these IDs.',
];

$orderIdFile = $stateDir . '/rz04-last-order-id.txt';
if (is_file($orderIdFile)) {
    $id = trim(file_get_contents($orderIdFile));
    if ($id !== '') {
        $report['createdDuringRun']['orders'][] = $id;
    }
}

$payIdFile = $stateDir . '/rz09-last-pay-id.txt';
if (is_file($payIdFile)) {
    $id = trim(file_get_contents($payIdFile));
    if ($id !== '') {
        $report['createdDuringRun']['payments'][] = $id;
    }
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);