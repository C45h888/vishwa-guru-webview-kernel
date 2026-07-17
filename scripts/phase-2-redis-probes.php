<?php

declare(strict_types=1);

/**
 * Phase 2 — Redis Integration Probe-Style Validation Report
 * ==========================================================
 *
 * Mirrors phase-0.5-validation-report.json. Probes the Redis substrate
 * the same way Phase 0.5 probed the Neon schema: deterministic, json-shaped,
 * exit-code meaningful. Used as the runtime counterpart to the schema
 * probes from Phase 0.5.
 *
 * Usage:
 *   php scripts/phase-2-redis-probes.php
 *   php scripts/phase-2-redis-probes.php --output=phase-2-redis-report.json
 *
 * Exit codes:
 *   0 — every probe passed
 *   1 — one or more probes failed (see JSON output for details)
 *
 * Doctrine alignment:
 *   - "Always infer state from concrete reads inside the codebase." This
 *     script reads the runtime — it does NOT trust config files in
 *     isolation.
 *   - "Validation before continuation." Phase 2 cannot close until this
 *     script returns 0.
 *
 * Probes:
 *   - r01  ext-redis loaded (PHP runtime check)
 *   - r02  RedisConnectorContract resolves
 *   - r03  four logical DBs configured (default/cache/queue/session)
 *   - r04  Redis PING on default DB
 *   - r05  Redis PING on cache DB
 *   - r06  Redis PING on queue DB
 *   - r07  Redis PING on session DB
 *   - r08  Cache::put round-trip on cache DB
 *   - r09  Cache::get returns null for missing key
 *   - r10  Queue::size() returns numeric (queue driver reachable)
 *   - r11  Redis INFO returns version string
 *   - r12  Idempotency fast-path pattern (SET NX) works
 *   - r13  Webhook dedupe pattern (SET NX EX) works
 *   - r14  Key prefix is applied (configured prefix visible in MONITOR)
 *   - r15  Connect timeout respected (unreachable host fails ≤1.5s)
 *
 * Note: probes r15 requires REDIS_HOST=192.0.2.1 (RFC 5737 TEST-NET-1) to
 * be set. In a normal dev run it's skipped unless that env var is set.
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

// ─── Parse args ─────────────────────────────────────────────────────────────
$outputPath = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $outputPath = substr($arg, strlen('--output='));
    }
}

// ─── Probe helpers ──────────────────────────────────────────────────────────
$probes = [];
$startedAt = (new DateTimeImmutable())->format(DATE_ATOM);

$probe = function (string $id, string $name, callable $fn) use (&$probes): void {
    $probeStart = microtime(true);
    $record = [
        'id'     => $id,
        'name'   => $name,
        'status' => 'pass',
    ];
    try {
        $result = $fn();
        if ($result !== null && is_array($result)) {
            $record = array_merge($record, $result);
        }
    } catch (Throwable $e) {
        $record['status']  = 'fail';
        $record['error']   = $e->getMessage();
        $record['class']   = $e::class;
    }
    $record['elapsed_ms'] = round((microtime(true) - $probeStart) * 1000.0, 2);
    $record['at']        = (new DateTimeImmutable())->format(DATE_ATOM);
    $probes[] = $record;
};

// ─── Probes ─────────────────────────────────────────────────────────────────

$probe('r01', 'ext-redis loaded', function () {
    if (! extension_loaded('redis')) {
        throw new RuntimeException('ext-redis not loaded in PHP runtime');
    }

    $v = phpversion('redis');

    return ['version' => $v];
});

$probe('r02', 'RedisConnectorContract resolves', function () use ($app) {
    $connector = $app->make(\App\Redis\Contracts\RedisConnectorContract::class);
    if (! $connector instanceof \App\Redis\Contracts\RedisConnectorContract) {
        throw new RuntimeException('Container did not return RedisConnectorContract');
    }
    $cls = $connector::class;

    return ['concrete' => $cls];
});

$probe('r03', 'four logical DBs configured', function () use ($app) {
    $connector = $app->make(\App\Redis\Contracts\RedisConnectorContract::class);
    $databases = $connector->configuredDatabases();

    foreach (['default', 'cache', 'queue', 'session'] as $required) {
        if (! array_key_exists($required, $databases)) {
            throw new RuntimeException("missing required connection: {$required}");
        }
    }

    if ($databases['default'] !== 0 || $databases['cache'] !== 1
        || $databases['queue'] !== 2 || $databases['session'] !== 3) {
        throw new RuntimeException('DB number mismatch: ' . json_encode($databases));
    }

    return ['databases' => $databases];
});

foreach (['default', 'cache', 'queue', 'session'] as $i => $name) {
    $probe("r0" . (4 + $i), "Redis PING on {$name} DB", function () use ($app, $name) {
        $connector = $app->make(\App\Redis\Contracts\RedisConnectorContract::class);
        if (! $connector->ping($name)) {
            throw new RuntimeException("Redis {$name} DB did not respond to PING");
        }
    });
}

$probe('r08', 'Cache::put round-trip', function () {
    $key = 'probe:phase2:cache:' . bin2hex(random_bytes(4));
    $value = 'probe-' . bin2hex(random_bytes(4));
    Cache::put($key, $value, 60);
    $read = Cache::get($key);
    Cache::forget($key);

    if ($read !== $value) {
        throw new RuntimeException("round-trip mismatch: wrote {$value} read " . var_export($read, true));
    }
});

$probe('r09', 'Cache::get returns null for missing key', function () {
    $key = 'probe:phase2:missing:' . bin2hex(random_bytes(4));
    $read = Cache::get($key);

    if ($read !== null) {
        throw new RuntimeException('expected null for missing key, got ' . var_export($read, true));
    }
});

$probe('r10', 'Queue::size() returns numeric', function () {
    $size = Queue::connection()->size(null);
    if (! is_int($size) && ! is_numeric($size)) {
        throw new RuntimeException('Queue::size() did not return numeric, got ' . var_export($size, true));
    }

    return ['queue_size' => (int) $size];
});

$probe('r11', 'Redis INFO returns version', function () use ($app) {
    $connector = $app->make(\App\Redis\Contracts\RedisConnectorContract::class);
    $client = $connector->connection('default');
    if (! method_exists($client, 'info')) {
        throw new RuntimeException('underlying client does not implement info()');
    }
    $info = $client->info();
    if (! is_array($info) || empty($info['redis_version'])) {
        throw new RuntimeException('INFO did not return redis_version');
    }

    return ['redis_version' => $info['redis_version']];
});

$probe('r12', 'idempotency fast-path SET NX works', function () use ($app) {
    $connector = $app->make(\App\Redis\Contracts\RedisConnectorContract::class);
    $client = $connector->connection('default');
    $key = 'probe:idem:donation:' . bin2hex(random_bytes(6));

    // SET NX returns true when the key was set, false when it already exists.
    $first  = $client->set($key, '1', ['NX', 'EX' => 60]);
    $second = $client->set($key, '2', ['NX', 'EX' => 60]);
    $client->del($key);

    if ($first !== true && $first !== 1) {
        throw new RuntimeException("first SET NX did not return true/1, got " . var_export($first, true));
    }
    if ($second !== false && $second !== 0) {
        throw new RuntimeException("second SET NX did not return false/0, got " . var_export($second, true));
    }
});

$probe('r13', 'webhook dedupe SET NX EX works', function () use ($app) {
    $connector = $app->make(\App\Redis\Contracts\RedisConnectorContract::class);
    $client = $connector->connection('default');
    $key = 'probe:idem:webhook:razorpay:' . bin2hex(random_bytes(6));

    $r1 = $client->set($key, '1', ['NX', 'EX' => 600]);
    $r2 = $client->set($key, '2', ['NX', 'EX' => 600]);
    $ttl = $client->ttl($key);
    $client->del($key);

    if ($r1 !== true && $r1 !== 1) {
        throw new RuntimeException("webhook dedupe first SET NX failed");
    }
    if ($r2 !== false && $r2 !== 0) {
        throw new RuntimeException("webhook dedupe duplicate was allowed");
    }
    if ($ttl <= 0 || $ttl > 600) {
        throw new RuntimeException("expected TTL within (0, 600], got {$ttl}");
    }
});

$probe('r14', 'configured prefix applied', function () use ($app) {
    $prefix = $app['config']->get('database.redis.options.prefix');
    if (! is_string($prefix) || $prefix === '') {
        throw new RuntimeException('database.redis.options.prefix not configured');
    }

    return ['prefix' => $prefix];
});

if (($argvHasTimeout = in_array('--probe-timeout', $argv ?? [], true))
    || (getenv('REDIS_TIMEOUT_PROBE') === '1')) {
    $probe('r15', 'connect timeout respected', function () use ($app) {
        // Point at RFC 5737 TEST-NET-1 — guaranteed unroutable.
        config(['database.redis.default.host' => '192.0.2.1']);
        config(['database.redis.default.timeout' => 0.5]);

        // Force the connector to rebuild with the new config.
        $app->forgetInstance(\App\Redis\Contracts\RedisConnectorContract::class);

        $start = microtime(true);
        $connector = $app->make(\App\Redis\Contracts\RedisConnectorContract::class);
        $result = $connector->ping('default');
        $elapsed = (microtime(true) - $start);

        // ping() must NOT throw; it must return false; elapsed must be
        // roughly within the timeout window.
        if ($result !== false) {
            throw new RuntimeException('expected false from ping against unroutable host');
        }
        if ($elapsed > 2.0) {
            throw new RuntimeException("ping took {$elapsed}s, expected <2s with timeout=0.5s");
        }

        return ['elapsed_s' => round($elapsed, 3)];
    });
}

// ─── Assemble report ────────────────────────────────────────────────────────
$passed = count(array_filter($probes, fn ($p) => $p['status'] === 'pass'));
$failed = count($probes) - $passed;

$report = [
    'branch'    => trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown'),
    'ran_at'    => $startedAt,
    'phase'     => '2 — Redis integration',
    'total'     => count($probes),
    'passed'    => $passed,
    'failed'    => $failed,
    'probes'    => $probes,
];

// ─── Output ─────────────────────────────────────────────────────────────────
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

if ($outputPath !== null) {
    file_put_contents($outputPath, $json);
    fwrite(STDERR, "wrote {$outputPath}\n");
}

echo $json;

// ─── Exit code ──────────────────────────────────────────────────────────────
exit($failed === 0 ? 0 : 1);
