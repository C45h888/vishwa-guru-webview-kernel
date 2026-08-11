<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$dsn = config('database.connections.neon.dsn');
echo 'DSN: ' . ($dsn ?: '(null)') . PHP_EOL;

// Fall back to host/db/username/password
if (! $dsn) {
    $conn = config('database.connections.neon');
    echo 'HOST: ' . ($conn['host'] ?? '') . PHP_EOL;
    echo 'PORT: ' . ($conn['port'] ?? '') . PHP_EOL;
    echo 'DATABASE: ' . ($conn['database'] ?? '') . PHP_EOL;
    echo 'USERNAME: ' . ($conn['username'] ?? '') . PHP_EOL;
    echo 'PASSWORD: ' . ($conn['password'] ?? '') . PHP_EOL;
    echo 'SSLMODE: ' . ($conn['sslmode'] ?? '') . PHP_EOL;
}
