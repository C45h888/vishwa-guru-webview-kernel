<?php
require "/app/vendor/autoload.php";
$adapter = new App\Payments\Infrastructure\Persistence\InMemoryAdapter(new App\Shared\Support\SystemClock(), new App\Shared\Support\UlidGenerator(), "/app/schema-neon/V1-schema.sql");
$reflection = new ReflectionMethod($adapter, "rewriteForSqlite");
$reflection->setAccessible(true);
$rewritten = $reflection->invoke($adapter, file_get_contents("/app/schema-neon/V1-schema.sql"));
$reflection = new ReflectionMethod($adapter, "splitStatements");
$reflection->setAccessible(true);
$statements = $reflection->invoke($adapter, $rewritten);
file_put_contents("/app/stmt0.sql", $statements[0]);
echo "Length: " . strlen($statements[0]) . "\n";
$pdo = new PDO("sqlite::memory:");
try {
    $pdo->exec($statements[0]);
    echo "OK\n";
} catch (PDOException $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
    echo "Statement 0 around the error position:\n";
    // Try executing piece by piece to find the offending line
    $lines = explode("\n", $statements[0]);
    $running = '';
    foreach ($lines as $i => $line) {
        $running .= $line . "\n";
        try {
            $pdo2 = new PDO("sqlite::memory:");
            $pdo2->exec($running);
        } catch (PDOException $e2) {
            if (strpos($e2->getMessage(), "syntax") !== false || strpos($e2->getMessage(), "near") !== false) {
                echo "Failed at line " . ($i+1) . ": " . trim($line) . "\n";
                echo "  Cumulative: " . substr($running, -200) . "\n";
                break;
            }
        }
    }
}