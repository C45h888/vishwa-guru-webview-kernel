<?php
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$config = config('database.connections.neon');
$pw = $config['password'];
echo 'TYPE: ' . gettype($pw) . PHP_EOL;
echo 'IS_NULL: ' . ($pw === null ? 'YES' : 'NO') . PHP_EOL;
echo 'VAR_DUMP:' . PHP_EOL;
var_dump($pw);

// And the URL
$url = $config['url'];
echo 'URL VAR_DUMP:' . PHP_EOL;
var_dump($url);

// Now actually try the connection with the resolved config
echo '--- PDO attributes ---' . PHP_EOL;
try {
    $pdo = \Illuminate\Support\Facades\DB::connection('neon')->getPdo();
    echo 'ATTR_CONNECTION_STATUS: ' . print_r($pdo->getAttribute(\PDO::ATTR_CONNECTION_STATUS), true);
} catch (\Throwable $e) {
    echo 'ERROR: ' . $e->getMessage() . PHP_EOL;
}
