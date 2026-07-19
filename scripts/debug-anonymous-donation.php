<?php

declare(strict_types=1);

/**
 * Focused verification for the DonationRepository JSONB-null fix.
 * Exercises the anonymous-donor code path that previously tripped
 * the donations_anonymous_no_pii CHECK constraint.
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Persistence\ValueObjects\EntityId;

$repo    = $app->make(DonationRepositoryContract::class);
$adapter = $app->make(\App\Persistence\Contracts\PersistenceAdapterContract::class);

// Discover or seed campaign
$campaign = $adapter->query("SELECT id FROM campaigns WHERE state = 'active' LIMIT 1")->value();
if (empty($campaign)) {
    fwrite(STDERR, "no active campaign — run ProductionSeeder first\n");
    exit(1);
}
$campaignId = $campaign[0]['id'];

// Seed idempotency key
$idemKey = 'jsonb-fix-verify-' . bin2hex(random_bytes(4));
$adapter->execute(
    "INSERT INTO idempotency_keys (key, scope, request_fingerprint, expires_at, created_at)
     VALUES (:k, 'donation', :fp, NOW() + INTERVAL '1 day', NOW())
     ON CONFLICT (key) DO NOTHING",
    ['k' => $idemKey, 'fp' => 'jsonb-fix-fp']
);

echo "=== Anonymous donor save (was failing with CHECK violation) ===\n";

$donation = Donation::draft(
    campaignId: EntityId::fromString($campaignId),
    donor: DonorIdentity::anonymous(),
    amountMinor: 100,
    currency: Currency::INR,
    donorId: null,
    internalNotes: "jsonb-fix-verify run_id={$idemKey}",
    idempotencyKey: $idemKey,
);

try {
    $repo->save($donation);
    echo "save: OK (donation_id=" . $donation->id()->value() . ")\n";
} catch (Throwable $e) {
    fwrite(STDERR, "save: FAIL — " . $e->getMessage() . "\n");
    exit(1);
}

// Verify the row exists with donor_address_snapshot = NULL (not '[]')
$row = $adapter->query(
    'SELECT id, donor_address_snapshot, metadata, is_anonymous FROM donations WHERE id = :id',
    ['id' => $donation->id()->value()]
)->value()[0];

echo "\n=== Row inspection ===\n";
echo "is_anonymous: " . ($row['is_anonymous'] ? 'true' : 'false') . "\n";
echo "donor_address_snapshot: " . var_export($row['donor_address_snapshot'], true) . "\n";
echo "metadata: " . var_export($row['metadata'], true) . "\n";

$assertions = [];
$assertions['is_anonymous_true']     = $row['is_anonymous'] === true || $row['is_anonymous'] === 't';
$assertions['address_is_null']       = $row['donor_address_snapshot'] === null;
$assertions['metadata_is_object']    = is_string($row['metadata']) ? ! empty($row['metadata']) : ! empty($row['metadata']);

echo "\n=== Assertions ===\n";
$ok = true;
foreach ($assertions as $name => $pass) {
    echo "  " . ($pass ? "PASS" : "FAIL") . " — " . $name . "\n";
    $ok = $ok && $pass;
}

// Cleanup
$adapter->execute('DELETE FROM donations WHERE id = :id', ['id' => $donation->id()->value()]);
$adapter->execute('DELETE FROM idempotency_keys WHERE key = :k', ['k' => $idemKey]);
echo "\ncleaned up: donation + idempotency_keys row\n";

exit($ok ? 0 : 1);
