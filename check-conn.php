<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Use Laravel's PDO connection to verify it works
try {
    $pdo = \Illuminate\Support\Facades\DB::connection('neon')->getPdo();
    echo 'CONNECTED_OK' . PHP_EOL;
    echo 'DRIVER: ' . $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) . PHP_EOL;
    $row = \Illuminate\Support\Facades\DB::connection('neon')->select("SELECT current_user, current_database(), inet_server_addr()::text AS ip");
    print_r($row);
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
