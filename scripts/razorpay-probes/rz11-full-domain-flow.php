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

// ─── Helpers ────────────────────────────────────────────────────────────────
function discoverOrSeedCampaign(\Illuminate\Foundation\Application $app, string $runId): string
{
    $adapter = $app->make(\App\Persistence\Contracts\PersistenceAdapterContract::class);

    $r = $adapter->query("SELECT id FROM campaigns WHERE deleted_at IS NULL ORDER BY created_at ASC LIMIT 1");
    if ($r->isFailure()) {
        throw new RuntimeException('campaign discovery failed: ' . $r->error());
    }
    $rows = $r->value();
    if (! empty($rows)) {
        return (string) $rows[0]['id'];
    }

    $currencyCheck = $adapter->query("SELECT code FROM currencies WHERE code = 'INR' LIMIT 1");
    if ($currencyCheck->isFailure()) {
        throw new RuntimeException('currencies lookup failed: ' . $currencyCheck->error());
    }
    if (empty($currencyCheck->value())) {
        $seedCurrency = $adapter->execute(
            "INSERT INTO currencies (code, name, symbol, minor_unit_digits, is_active, display_order, created_at, updated_at)
             VALUES ('INR', 'Indian Rupee', '₹', 2, TRUE, 0, NOW(), NOW())"
        );
        if ($seedCurrency->isFailure()) {
            throw new RuntimeException('failed to seed INR: ' . $seedCurrency->error());
        }
    }

    $probeCampaignId = 'cmp-rz11-' . $runId;
    $now = (new DateTimeImmutable())->format(DATE_ATOM);
    $insert = $adapter->execute(
        "INSERT INTO campaigns (
             id, slug, title, description, short_description, category,
             state, currency_code, target_amount_minor,
             is_featured, display_order, starts_at, ends_at,
             metadata, created_at, updated_at
         ) VALUES (
             '{$probeCampaignId}', 'rz11-probe-{$runId}', 'rz11 probe campaign',
             'probe', 'probe', 'general', 'active', 'INR', NULL,
             FALSE, 0, '{$now}', NULL, '{}', '{$now}', '{$now}'
         )"
    );
    if ($insert->isFailure()) {
        throw new RuntimeException('failed to seed campaign: ' . $insert->error());
    }

    return $probeCampaignId;
}

// ─── Step A: Discover-or-seed a campaign ────────────────────────────────────
$runId = 'rz11-' . bin2hex(random_bytes(6));
$campaignId = discoverOrSeedCampaign($app, $runId);

// ─── Step B: Initialize a new donation via PaymentService ───────────────────
$paymentService = $app->make(\App\Payments\Services\PaymentService::class);

$donor = \App\Payments\Domain\ValueObjects\DonorIdentity::identified(
    name: 'rz11 donor',
    email: 'rz11@example.in',
    phone: '+919999999999',
);

$idempotencyKey = $runId;

$intent = new \App\Payments\Domain\ValueObjects\DonationIntent(
    campaignId: new \App\Shared\ValueObjects\Identifier($campaignId),
    donor: $donor,
    amountMinor: 50000,
    currency: \App\Payments\Domain\Enums\Currency::INR,
    dedication: null,
    donorMessage: null,
    internalNotes: "probe rz11 runId={$runId}",
    idempotencyKey: $idempotencyKey,
    metadata: ['probe' => 'rz11', 'run_id' => $runId],
);

$initResult = $paymentService->initialize($intent);
if (! $initResult->isOk()) {
    respond('rz11', [['name' => 'PaymentService::initialize ok', 'passed' => false]], [
        'error'      => $initResult->error(),
        'campaignId' => $campaignId,
    ], (int) ($start * 1000));
}

$paymentResult = $initResult->value();
$orderIdFromInit = $paymentResult->gatewayOrderId();

// ─── Step C: Simulate webhook delivery (no real HTTP transport) ─────────────
$paymentId  = 'pay_rz11_' . bin2hex(random_bytes(4));
$createdAt  = time();
$amount     = 50000;
$donationId = $runId;

$fixture = file_get_contents(__DIR__ . '/fixtures/webhook-payment-captured.json');
$payloadRaw = strtr($fixture, [
    '__PAYMENT_ID__'  => $paymentId,
    '__ORDER_ID__'    => $orderIdFromInit,
    '__AMOUNT__'      => (string) $amount,
    '__DONATION_ID__' => $donationId,
    '__RECEIPT__'     => $idempotencyKey,
    '__CREATED_AT__'  => (string) $createdAt,
]);
$payloadArr  = json_decode($payloadRaw, true);
$payloadJson = json_encode($payloadArr, JSON_UNESCAPED_SLASHES);

$secret = (string) config('payments.providers.razorpay.webhook_secret');
$header = (string) config('payments.providers.razorpay.webhook_signature_header', 'X-Razorpay-Signature');
$hmac   = hash_hmac('sha256', $payloadJson, $secret);

// PaymentOrchestrator::handleWebhook reads gateway_order_id from payload
// metadata (not from headers). We populate metadata so it can locate
// the local payment.
$webhookPayload = new \App\Payments\Domain\ValueObjects\WebhookPayload(
    provider: \App\Payments\Domain\Enums\PaymentProvider::RAZORPAY,
    headers: [$header => $hmac],
    rawBody: $payloadJson,
    receivedAt: new DateTimeImmutable(),
    providerEventId: 'evt_rz11_' . bin2hex(random_bytes(4)),
    metadata: [
        'gateway_order_id'   => $orderIdFromInit,
        'gateway_payment_id' => $paymentId,
        'expected_amount_minor' => $amount,
        'expected_currency'     => 'INR',
    ],
);

$handleResult = $paymentService->handleWebhook($webhookPayload);

$checks = [
    ['name' => 'PaymentService::initialize returned ok',          'passed' => $initResult->isOk()],
    ['name' => 'gatewayOrderId from initialize is order_*',       'passed' => str_starts_with($orderIdFromInit, 'order_')],
    ['name' => 'amount === 50000',                                'passed' => $paymentResult->amountMinor() === 50000],
    ['name' => 'currency === INR',                                'passed' => $paymentResult->currency() === \App\Payments\Domain\Enums\Currency::INR],
    ['name' => 'PaymentService::handleWebhook returned ok',       'passed' => $handleResult->isOk()],
];

$evidence = [
    'campaignId'         => $campaignId,
    'initializedOrderId' => $orderIdFromInit,
    'paymentId'          => $paymentId,
    'donationId'         => $donationId,
    'runId'              => $runId,
    'initResult'         => 'ok',
    'webhookResult'      => $handleResult->isOk() ? 'ok' : ($handleResult->error() ?? 'unknown'),
];

respond('rz11', $checks, $evidence, (int) ($start * 1000));