<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$adapter = $app->make(\App\Persistence\Contracts\PersistenceAdapterContract::class);

$cid = $adapter->query("SELECT id FROM campaigns WHERE deleted_at IS NULL LIMIT 1")->value()[0]['id'] ?? null;
if (!$cid) { echo "no campaign\n"; exit; }
echo "Using campaign: $cid\n";

$markerId = 'tx-iso-' . bin2hex(random_bytes(4));
echo "Marker id: $markerId\n";

// Test 1: visibility INSIDE the transaction (before commit)
$r1 = $adapter->transaction(function () use ($adapter, $cid, $markerId) {
    $exec = $adapter->execute(
        "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                               is_anonymous, state, idempotency_key,
                               submitted_at, created_at, updated_at)
         VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
                NULL, NOW(), NOW())",
        ['id' => $markerId, 'cid' => $cid]
    );
    echo "  INSIDE tx: insert = " . ($exec->isFailure() ? "FAIL: " . $exec->error() : "ok ({$exec->value()} rows)") . "\n";
    if ($exec->isFailure()) {
        throw new RuntimeException('insert failed');
    }

    // Check visibility WITHIN the transaction
    $insideCheck = $adapter->query("SELECT id FROM donations WHERE id = :id", ['id' => $markerId])->value();
    echo "  INSIDE tx: post-insert SELECT = " . count($insideCheck) . " rows\n";

    return $exec->value();
});
echo "Test 1 (inside-tx visibility): " . ($r1->isFailure() ? "FAIL" : "ok") . "\n";

// Check visibility AFTER the transaction commits
$outsideCheck = $adapter->query("SELECT id FROM donations WHERE id = :id", ['id' => $markerId])->value();
echo "Test 1 (outside-tx visibility): " . count($outsideCheck) . " rows\n";

$adapter->execute("DELETE FROM donations WHERE id = :id", ['id' => $markerId]);
echo "cleaned up\n\n";

// Test 2: direct execute without transaction wrapper (auto-commit)
$markerId2 = 'tx-auto-' . bin2hex(random_bytes(4));
echo "Test 2: marker id = $markerId2 (direct auto-commit)\n";
$exec = $adapter->execute(
    "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                           is_anonymous, state, idempotency_key,
                           submitted_at, created_at, updated_at)
     VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
            NULL, NOW(), NOW())",
    ['id' => $markerId2, 'cid' => $cid]
);
echo "  Direct insert: " . ($exec->isFailure() ? "FAIL: " . $exec->error() : "ok ({$exec->value()} rows)") . "\n";
$check2 = $adapter->query("SELECT id FROM donations WHERE id = :id", ['id' => $markerId2])->value();
echo "  Post-insert SELECT: " . count($check2) . " rows\n";
$adapter->execute("DELETE FROM donations WHERE id = :id", ['id' => $markerId2]);
echo "  cleaned up\n";
