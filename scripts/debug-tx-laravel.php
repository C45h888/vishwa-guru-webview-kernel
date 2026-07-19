<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$adapter = $app->make(\App\Persistence\Contracts\PersistenceAdapterContract::class);

// Discover a campaign
$campaignId = $adapter->query("SELECT id FROM campaigns WHERE deleted_at IS NULL LIMIT 1")->value()[0]['id'] ?? null;
if (!$campaignId) { echo "no campaign\n"; exit; }

$marker = 'lara-' . bin2hex(random_bytes(4));
echo "Marker: $marker\n";

// Hook into the Connection's transaction methods to log what happens
$reflection = new ReflectionClass($adapter);
foreach (['connection'] as $prop) {
    if ($reflection->hasProperty($prop)) {
        $p = $reflection->getProperty($prop);
        $p->setAccessible(true);
        $conn = $p->getValue($adapter);
        break;
    }
}

echo "\n=== Laravel transaction with diagnostic Event listeners ===\n";

// Hook events
$dispatcher = $app->make('events');
$dispatcher->listen('Illuminate\Database\Events\TransactionBeginning', function ($event) use (&$beginFired) {
    $beginFired = true;
    echo "  [event] TransactionBeginning fired (name={$event->connectionName})\n";
});
$dispatcher->listen('Illuminate\Database\Events\TransactionCommitted', function ($event) use (&$commitFired) {
    $commitFired = true;
    echo "  [event] TransactionCommitted fired (name={$event->connectionName})\n";
});
$dispatcher->listen('Illuminate\Database\Events\TransactionRolledBack', function ($event) use (&$rollbackFired) {
    $rollbackFired = true;
    echo "  [event] TransactionRolledBack fired (name={$event->connectionName})\n";
});

echo "Adapter transaction call:\n";
$r = $adapter->transaction(function () use ($adapter, $campaignId, $marker) {
    $ins = $adapter->execute(
        "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                               is_anonymous, state, idempotency_key,
                               submitted_at, created_at, updated_at)
         VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
                NULL, NOW(), NOW())",
        ['id' => $marker, 'cid' => $campaignId]
    );
    echo "  INSERT result: " . ($ins->isFailure() ? "FAIL ".$ins->error() : "ok ({$ins->value()} rows)") . "\n";

    // Check inside the tx
    $inside = $adapter->query("SELECT id FROM donations WHERE id = :id", ['id' => $marker])->value();
    echo "  SELECT inside tx: " . count($inside) . " rows\n";

    return $ins->value();
});

echo "TX wrapper result: " . ($r->isFailure() ? "FAIL: ".$r->error() : "ok ({$r->value()})") . "\n";

echo "Events fired: begin=" . ($beginFired ?? 'no') . " commit=" . ($commitFired ?? 'no') . " rollback=" . ($rollbackFired ?? 'no') . "\n";

// Check outside the tx
$outside = $adapter->query("SELECT id FROM donations WHERE id = :id", ['id' => $marker])->value();
echo "SELECT outside tx: " . count($outside) . " rows\n";

// Inspect PDO transaction level via raw PDO reflection
$pdoRef = new ReflectionClass($conn);
if ($pdoRef->hasProperty('pdo')) {
    $pdoProp = $pdoRef->getProperty('pdo');
    $pdoProp->setAccessible(true);
    $pdoClosure = $pdoProp->getValue($conn);
    if ($pdoClosure instanceof Closure) {
        // Call the closure to get the actual PDO
        $realPdo = $pdoClosure();
        if ($realPdo instanceof PDO) {
            echo "PDO inTransaction() = " . ($realPdo->inTransaction() ? 'true' : 'false') . "\n";
        }
    }
}

$adapter->execute("DELETE FROM donations WHERE id = :id", ['id' => $marker]);
echo "\ncleaned up\n";
