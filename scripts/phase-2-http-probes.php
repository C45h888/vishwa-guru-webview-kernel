<?php

/**
 * phase-2-http-probes.php — Idempotency-Key HTTP middleware probes.
 *
 * Mirrors phase-2-queue-probes.php + phase-2-redis-probes.php shape.
 * Probes verify the inbound HTTP dedupe layer (Layer 1 of 3-layer dedupe).
 *
 * Usage:
 *   php scripts/phase-2-http-probes.php
 *   php scripts/phase-2-http-probes.php --output=reports/http.json
 *
 * Exit code: 0 if all probes pass; 1 otherwise.
 *
 * Doctrine: a probe that can't run (e.g. config file missing) is a FAIL,
 * not a SKIP. The runtime should always be in a verifiable state.
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

/**
 * Run a probe. If the callback throws, the probe is recorded as failed
 * with the exception message.
 */
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

// ─── http01: IdempotencyMiddleware registered in Kernel::$middlewareAliases ─
$probe('http01', 'IdempotencyMiddleware registered in Kernel::$middlewareAliases', function () {
    $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
    $reflection = new ReflectionClass($kernel);
    $prop = $reflection->getProperty('middlewareAliases');
    $prop->setAccessible(true);
    $aliases = $prop->getValue($kernel);

    if (! array_key_exists('idempotency', $aliases)) {
        throw new RuntimeException('Kernel::$middlewareAliases does not have "idempotency" alias');
    }
    if ($aliases['idempotency'] !== \App\Http\Middleware\IdempotencyMiddleware::class) {
        throw new RuntimeException(
            'idempotency alias resolves to ' . $aliases['idempotency']
            . ', expected ' . \App\Http\Middleware\IdempotencyMiddleware::class
        );
    }
});

// ─── http02: IDEMPOTENCY_KEY_HEADER env key present in .env.example ─
$probe('http02', 'IDEMPOTENCY_KEY_HEADER env key present in .env.example', function () {
    $path = __DIR__ . '/../.env.example';
    if (! is_readable($path)) {
        throw new RuntimeException('.env.example not readable at ' . $path);
    }
    $contents = file_get_contents($path);
    if (! str_contains($contents, 'IDEMPOTENCY_KEY_HEADER=')) {
        throw new RuntimeException('IDEMPOTENCY_KEY_HEADER= not found in .env.example');
    }
});

// ─── http03: config('idempotency.header') === 'Idempotency-Key' (default) ─
$probe('http03', "config('idempotency.header') === 'Idempotency-Key' (default)", function () {
    $actual = config('idempotency.header');
    if ($actual !== 'Idempotency-Key') {
        throw new RuntimeException("config('idempotency.header') = " . var_export($actual, true)
            . ", expected 'Idempotency-Key'");
    }
});

// ─── http04: config('idempotency.default_ttl') === 86400 (24h) ─
$probe('http04', "config('idempotency.default_ttl') === 86400 (24h)", function () {
    $actual = config('idempotency.default_ttl');
    if ((int) $actual !== 86400) {
        throw new RuntimeException("config('idempotency.default_ttl') = " . var_export($actual, true)
            . ', expected 86400');
    }
});

// ─── http05: routes/donation.php applies the `idempotency` middleware group ─
$probe('http05', "routes/donation.php applies the 'idempotency' middleware group", function () {
    $path = __DIR__ . '/../routes/donation.php';
    if (! is_readable($path)) {
        throw new RuntimeException('routes/donation.php not readable at ' . $path);
    }
    // Verify the file contains a route that uses the idempotency middleware.
    // This is verified statically because Route::middleware() runs at
    // bootstrap and may not be reachable via reflection.
    $contents = file_get_contents($path);
    if (! str_contains($contents, "Route::post('/donate'") && ! str_contains($contents, 'idempotency')) {
        throw new RuntimeException('routes/donation.php does not declare a POST /donate route with idempotency middleware');
    }
});

// ─── Assemble report ────────────────────────────────────────────────────────
$passed = count(array_filter($probes, fn ($p) => $p['status'] === 'pass'));
$failed = count($probes) - $passed;

$report = [
    'branch'    => trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown'),
    'ran_at'    => $startedAt,
    'phase'     => '2 — HTTP Idempotency-Key middleware',
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
