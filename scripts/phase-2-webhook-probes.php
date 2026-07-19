<?php

/**
 * phase-2-webhook-probes.php — Webhook dedupe middleware probes.
 *
 * Mirrors phase-2-http-probes.php + phase-2-queue-probes.php shape.
 * Probes verify the inbound webhook dedupe layer (Layer 3 of 3-layer
 * dedupe) — the WebhookDedupeMiddleware and its config + route wiring.
 *
 * Usage:
 *   php scripts/phase-2-webhook-probes.php
 *   php scripts/phase-2-webhook-probes.php --output=reports/webhook.json
 *
 * Exit code: 0 if all probes pass; 1 otherwise.
 *
 * Doctrine: a probe that can't run is a FAIL, not a SKIP. The runtime
 * should always be in a verifiable state.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ─── Args ─────────────────────────────────────────────────────────────────
$outputPath = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $outputPath = substr($arg, 9);
    }
}

$startedAt = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);

// ─── Probes ────────────────────────────────────────────────────────────────
$probes = [];

$probe = function (string $id, string $name, callable $fn) use (&$probes) {
    $probeStartedAt = (new DateTimeImmutable())->format(DateTimeInterface::ATOM);
    try {
        $fn();
        $probes[] = [
            'id'        => $id,
            'name'      => $name,
            'kind'      => 'happy',
            'status'    => 'pass',
            'started_at' => $probeStartedAt,
        ];
    } catch (Throwable $e) {
        $probes[] = [
            'id'        => $id,
            'name'      => $name,
            'kind'      => 'happy',
            'status'    => 'fail',
            'started_at' => $probeStartedAt,
            'exception' => $e->getMessage(),
        ];
    }
};

// ─── wh01: WebhookDedupeMiddleware registered in Kernel::$middlewareAliases ─
$probe('wh01', 'WebhookDedupeMiddleware registered in Kernel::$middlewareAliases', function () {
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $reflection = new ReflectionClass($kernel);
    $prop = $reflection->getProperty('middlewareAliases');
    $prop->setAccessible(true);
    $aliases = $prop->getValue($kernel);

    if (! array_key_exists('webhook-dedupe', $aliases)) {
        throw new RuntimeException('Kernel::$middlewareAliases does not have "webhook-dedupe" alias');
    }
    if ($aliases['webhook-dedupe'] !== \App\Http\Middleware\WebhookDedupeMiddleware::class) {
        throw new RuntimeException(
            'webhook-dedupe alias resolves to ' . $aliases['webhook-dedupe']
            . ', expected ' . \App\Http\Middleware\WebhookDedupeMiddleware::class
        );
    }
});

// ─── wh02: config('webhook.default_ttl') === 604800 (7d) ─
$probe('wh02', "config('webhook.default_ttl') === 604800 (7d)", function () {
    $actual = config('webhook.default_ttl');
    if ((int) $actual !== 604800) {
        throw new RuntimeException("config('webhook.default_ttl') = " . var_export($actual, true)
            . ', expected 604800');
    }
});

// ─── wh03: routes/webhook.php applies the `webhook-dedupe` middleware group ─
$probe('wh03', "routes/webhook.php applies the 'webhook-dedupe' middleware group", function () {
    $path = __DIR__ . '/../routes/webhook.php';
    if (! is_readable($path)) {
        throw new RuntimeException('routes/webhook.php not readable at ' . $path);
    }
    $contents = file_get_contents($path);
    if (! str_contains($contents, "Route::post('/razorpay'") || ! str_contains($contents, "Route::post('/paypal'"))) {
        throw new RuntimeException('routes/webhook.php does not declare POST /razorpay and POST /paypal routes');
    }
    if (! str_contains($contents, 'webhook-dedupe')) {
        throw new RuntimeException('routes/webhook.php does not apply the webhook-dedupe middleware');
    }
});

// ─── wh04: config('webhook.providers.razorpay.header') === 'X-Razorpay-Event-Id' ─
$probe('wh04', "config('webhook.providers.razorpay.header') === 'X-Razorpay-Event-Id'", function () {
    $actual = config('webhook.providers.razorpay.header');
    if ($actual !== 'X-Razorpay-Event-Id') {
        throw new RuntimeException("config('webhook.providers.razorpay.header') = " . var_export($actual, true)
            . ", expected 'X-Razorpay-Event-Id'");
    }
});

// ─── wh05: config('webhook.providers.paypal.header') === 'PAYPAL-TRANSMISSION-ID' ─
$probe('wh05', "config('webhook.providers.paypal.header') === 'PAYPAL-TRANSMISSION-ID'", function () {
    $actual = config('webhook.providers.paypal.header');
    if ($actual !== 'PAYPAL-TRANSMISSION-ID') {
        throw new RuntimeException("config('webhook.providers.paypal.header') = " . var_export($actual, true)
            . ", expected 'PAYPAL-TRANSMISSION-ID'");
    }
});

// ─── Assemble report ────────────────────────────────────────────────────────
$passed = count(array_filter($probes, fn ($p) => $p['status'] === 'pass'));
$failed = count($probes) - $passed;

$report = [
    'branch'    => trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown'),
    'ran_at'    => $startedAt,
    'phase'     => '2 — Webhook Dedupe middleware (Layer 3)',
    'total'     => count($probes),
    'passed'    => $passed,
    'failed'    => $failed,
    'probes'    => $probes,
];

$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($outputPath !== null) {
    file_put_contents($outputPath, $json);
    fwrite(STDERR, "wrote {$outputPath}\n");
}

echo $json;

exit($failed === 0 ? 0 : 1);
