<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

set_error_handler(function ($severity, $message, $file, $line) {
    if (str_contains($message, 'count():')) {
        echo "\n!!! count() ERROR captured !!!\n";
        echo "message: $message\n";
        echo "file: $file\n";
        echo "line: $line\n";
        echo "trace:\n";
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        echo "\n";
        return true;
    }
    return false;
});

set_exception_handler(function ($e) {
    echo "EXCEPTION: " . $e::class . ": " . $e->getMessage() . "\n";
    echo "trace:\n";
    foreach ($e->getTrace() as $i => $frame) {
        echo "  #$i " . ($frame['file'] ?? '?') . ":" . ($frame['line'] ?? '?') . " " . ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? '') . "\n";
    }
});

$adapter = $app->make(\App\Persistence\Contracts\PersistenceAdapterContract::class);
$cid = $adapter->query("SELECT id FROM campaigns WHERE deleted_at IS NULL LIMIT 1")->value()[0]['id'] ?? null;
if (!$cid) { echo "no campaign\n"; exit; }
$marker = 'st-' . bin2hex(random_bytes(4));
echo "Marker: $marker\n";

$r = $adapter->transaction(function () use ($adapter, $cid, $marker) {
    echo "  [in callback]\n";
    $ins = $adapter->execute(
        "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                               is_anonymous, state, idempotency_key,
                               submitted_at, created_at, updated_at)
         VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
                NULL, NOW(), NOW())",
        ['id' => $marker, 'cid' => $cid]
    );
    echo "  INSERT: " . ($ins->isFailure() ? "FAIL ".$ins->error() : "ok ({$ins->value()})") . "\n";

    $selResult = $adapter->query("SELECT id FROM donations WHERE id = :id", ['id' => $marker]);
    echo "  SELECT result: " . ($selResult->isFailure() ? "FAIL: ".$selResult->error() : "ok (".count($selResult->value())." rows)") . "\n";

    if ($selResult->isFailure()) {
        throw new RuntimeException('SELECT inside tx failed: '.$selResult->error());
    }
    return $ins->value();
});

echo "TX: " . ($r->isFailure() ? "FAIL ".$r->error() : "ok ({$r->value()})") . "\n";

$adapter->execute("DELETE FROM donations WHERE id = :id", ['id' => $marker]);
echo "cleaned up\n";
