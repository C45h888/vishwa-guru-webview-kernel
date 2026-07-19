<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = getenv('DATABASE_URL');
$parts = parse_url($url);
$dsn = sprintf(
    'pgsql:host=%s;port=%d;dbname=%s;sslmode=require',
    $parts['host'],
    $parts['port'] ?? 5432,
    ltrim($parts['path'] ?? '/', '/')
);

$campaignId = $app->make(\App\Persistence\Contracts\PersistenceAdapterContract::class)
    ->query("SELECT id FROM campaigns WHERE deleted_at IS NULL LIMIT 1")->value()[0]['id'] ?? null;
if (!$campaignId) { echo "no campaign\n"; exit; }

$marker = 'rawpdo-' . bin2hex(random_bytes(4));
echo "Marker: $marker\n";

$pdo = new PDO($dsn, $parts['user'], $parts['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// Use a raw PDO transaction (no Laravel wrapping)
echo "\n=== Raw PDO BEGIN + INSERT + SELECT-inside + COMMIT + SELECT-outside ===\n";

$pdo->beginTransaction();
$ins = $pdo->prepare(
    "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                           is_anonymous, state, idempotency_key,
                           submitted_at, created_at, updated_at)
     VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
            NULL, NOW(), NOW())"
);
$ins->execute(['id' => $marker, 'cid' => $campaignId]);
echo "INSERT affected: " . $ins->rowCount() . "\n";

$sel = $pdo->prepare("SELECT id FROM donations WHERE id = :id");
$sel->execute(['id' => $marker]);
echo "SELECT-inside-tx rows: " . count($sel->fetchAll(PDO::FETCH_ASSOC)) . "\n";

$pdo->commit();
echo "commit() returned: true\n";

$sel2 = $pdo->prepare("SELECT id FROM donations WHERE id = :id");
$sel2->execute(['id' => $marker]);
echo "SELECT-outside-tx rows: " . count($sel2->fetchAll(PDO::FETCH_ASSOC)) . "\n";

// Cleanup
$pdo->exec("DELETE FROM donations WHERE id = '$marker'");
echo "\ncleaned up\n";
