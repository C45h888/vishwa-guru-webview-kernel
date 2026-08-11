<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Get the resolved config for the neon connection
$config = config('database.connections.neon');
foreach (['url','host','port','database','username','password','sslmode'] as $k) {
    $v = $config[$k] ?? '(null)';
    if (in_array($k, ['password','url']) && $v) {
        $v = '<' . strlen($v) . ' chars> ' . substr($v, 0, 5) . '...' . substr($v, -5);
    }
    echo str_pad($k, 12) . ' = ' . $v . PHP_EOL;
}

// Also try the raw env
echo '--- RAW ENV ---' . PHP_EOL;
foreach (['DB_PASSWORD','DATABASE_URL'] as $k) {
    $v = env($k);
    echo $k . ' = ' . ($v === null ? '(null)' : ('<' . strlen($v) . ' chars> ' . substr($v, 0, 5) . '...')) . PHP_EOL;
}

// And direct from getenv (which is what the SAPI-level env loader sees)
echo '--- getenv() ---' . PHP_EOL;
foreach (['DB_PASSWORD','DATABASE_URL'] as $k) {
    $v = getenv($k);
    echo $k . ' = ' . ($v === false ? '(false)' : ('<' . strlen($v) . ' chars> ' . substr($v, 0, 5) . '...')) . PHP_EOL;
}
