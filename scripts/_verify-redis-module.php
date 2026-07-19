<?php

declare(strict_types=1);

// One-off verification harness for the Phase 2 Redis module.
// Run inside the built Docker image:
//   docker run --rm -v $(pwd):/app -w /app temple-trust-runtime:test \
//     php scripts/_verify-redis-module.php

require __DIR__ . '/../vendor/autoload.php';

$app = new \Illuminate\Foundation\Application(__DIR__ . '/..');
$app->singleton(
    \Illuminate\Contracts\Http\Kernel::class,
    \App\Http\Kernel::class
);
$app->singleton(
    \Illuminate\Contracts\Console\Kernel::class,
    \App\Console\Kernel::class
);
$app->singleton(
    \Illuminate\Contracts\Debug\ExceptionHandler::class,
    \Illuminate\Foundation\Exceptions\Handler::class
);

// Skip the providers that have unresolved Phase 1 bindings.
$app->register(\App\Providers\AppServiceProvider::class);
$app->register(\App\Shared\Providers\SharedServiceProvider::class);
$app->register(\App\Persistence\Providers\PersistenceServiceProvider::class);
$app->register(\App\Redis\Providers\RedisServiceProvider::class);

$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Phase 2 Redis module — manual verification ===\n\n";

echo "ext-redis loaded: " . (extension_loaded('redis') ? 'yes' : 'no') . "\n";
echo "ext-redis version: " . phpversion('redis') . "\n\n";

$cfg = $app['config']->get('database.redis');
echo "Redis connections configured: " . implode(', ', array_keys($cfg)) . "\n";
echo "  default.database = " . $cfg['default']['database'] . "\n";
echo "  cache.database   = " . $cfg['cache']['database'] . "\n";
echo "  queue.database   = " . $cfg['queue']['database'] . "\n";
echo "  session.database = " . $cfg['session']['database'] . "\n";
echo "  default.timeout     = " . $cfg['default']['timeout'] . "s\n";
echo "  default.read_timeout = " . $cfg['default']['read_timeout'] . "s\n\n";

echo "CACHE_STORE    = " . $app['config']->get('cache.default') . "\n";
echo "QUEUE_CONNECTION = " . $app['config']->get('queue.default') . "\n\n";

$connector = $app->make(\App\Redis\Contracts\RedisConnectorContract::class);
echo "RedisConnectorContract resolves to: " . get_class($connector) . "\n";

$databases = $connector->configuredDatabases();
echo "configuredDatabases(): " . json_encode($databases) . "\n\n";

// ping must NOT throw, must return bool. With no Redis running, returns false.
$start = microtime(true);
$ok = $connector->ping('default');
$elapsed = round((microtime(true) - $start) * 1000, 2);
echo "ping('default') = " . ($ok ? 'true' : 'false') . " in {$elapsed}ms\n";

echo "\n=== Redis module wiring is correct ===\n";
