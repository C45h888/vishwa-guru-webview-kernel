<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$adapter = $app->make(\App\Persistence\Contracts\PersistenceAdapterContract::class);
$cid = $adapter->query("SELECT id FROM campaigns WHERE deleted_at IS NULL LIMIT 1")->value()[0]['id'] ?? null;
if (!$cid) { echo "no campaign\n"; exit; }

$marker = 'iso-' . bin2hex(random_bytes(4));
echo "Marker: $marker, campaign_id: $cid\n";

// Run the EXACT same INSERT inside transaction vs outside
echo "\n=== TEST 1: INSERT outside transaction (raw auto-commit) ===\n";
$ins = $adapter->execute(
    "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                           is_anonymous, state, idempotency_key,
                           submitted_at, created_at, updated_at)
     VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
            NULL, NOW(), NOW())",
    ['id' => $marker, 'cid' => $cid]
);
echo "  Result: " . ($ins->isFailure() ? "FAIL: ".$ins->error() : "ok ({$ins->value()} rows)") . "\n";
$adapter->execute('DELETE FROM donations WHERE id = :id', ['id' => $marker]);

echo "\n=== TEST 2: same INSERT inside transaction ===\n";
$marker2 = 'iso2-' . bin2hex(random_bytes(4));
try {
    $r = $adapter->transaction(function () use ($adapter, $cid, $marker2) {
        $ins = $adapter->execute(
            "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                                   is_anonymous, state, idempotency_key,
                                   submitted_at, created_at, updated_at)
             VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
                    NULL, NOW(), NOW())",
            ['id' => $marker2, 'cid' => $cid]
        );
        echo "  INSIDE: Result: " . ($ins->isFailure() ? "FAIL: ".$ins->error() : "ok ({$ins->value()} rows)") . "\n";
        return $ins->value();
    });
    echo "  TX wrapper: " . ($r->isFailure() ? "FAIL: ".$r->error() : "ok ({$r->value()})") . "\n";

    // Now try the post-commit SELECT through the adapter (this is what db09 does)
    $sel = $adapter->query('SELECT id FROM donations WHERE id = :id', ['id' => $marker2]);
    echo "  Post-commit SELECT via adapter: " . ($sel->isFailure() ? "FAIL: ".$sel->error() : "ok (".count($sel->value())." rows)") . "\n";
} catch (Throwable $e) {
    echo "  CAUGHT: " . $e::class . ": " . $e->getMessage() . "\n";
}
$adapter->execute('DELETE FROM donations WHERE id = :id', ['id' => $marker2]);

echo "\n=== TEST 3: same INSERT directly via DB facade inside transaction ===\n";
$marker3 = 'iso3-' . bin2hex(random_bytes(4));
try {
    DB::connection('neon')->transaction(function () use ($cid, $marker3) {
        $count = DB::connection('neon')->insert(
            "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                                   is_anonymous, state, idempotency_key,
                                   submitted_at, created_at, updated_at)
             VALUES (?, ?, NULL, 1, 'INR', TRUE, 'draft', NULL,
                    NULL, NOW(), NOW())",
            [$marker3, $cid]
        );
        echo "  INSIDE: insert returned: " . var_export($count, true) . "\n";
        $rows = DB::connection('neon')->select("SELECT id FROM donations WHERE id = ?", [$marker3]);
        echo "  INSIDE: select count: " . count($rows) . "\n";
    });
    echo "  TX wrapper: ok\n";
} catch (Throwable $e) {
    echo "  CAUGHT: " . $e::class . ": " . $e->getMessage() . "\n";
}
$adapter->execute('DELETE FROM donations WHERE id = :id', ['id' => $marker3]);
