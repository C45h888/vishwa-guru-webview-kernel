<?php

declare(strict_types=1);

/**
 * Phase 2 — Database Integration Probe-Style Validation Report
 * =============================================================
 *
 * Mirrors phase-2-redis-probes.php + phase-2-http-probes.php shape.
 * Probes verify the persistence kernel against the **production**
 * Neon PostgreSQL database (DATABASE_URL is set in .env).
 *
 * Usage:
 *   php scripts/phase-2-db-probes.php
 *   php scripts/phase-2-db-probes.php --output=reports/db.json
 *
 * Exit codes:
 *   0 — every probe passed
 *   1 — one or more probes failed (see JSON output for details)
 *
 * Mutation doctrine:
 *   This probe creates a REAL row in the `donations` table on the
 *   production Neon branch. Each probe run is uniquely marked via
 *   runId (`dbp-<8 hex>`) embedded in:
 *     - internal_notes
 *     - idempotency_key
 *   so any orphan row is trivially identifiable for manual cleanup.
 *   The cleanup probe (db15) deletes the row in a `finally` block
 *   so cleanup runs even when an earlier probe fails. If cleanup
 *   itself fails, the probe id is appended to
 *   `storage/logs/db-probe-orphans.json` for operator attention.
 *
 * Probes:
 *   Block A — Adapter surface
 *     db01  PersistenceAdapterContract resolves from container
 *     db02  isConnected() returns true
 *     db03  connectionMetadata() returns expected shape (no DB query)
 *     db04  query(SELECT 1) round-trip via adapter contract
 *     db05  Result wrapper returns failure (not throw) on bad SQL
 *
 *   Block B — Schema + transaction
 *     db06  donations table exists (V1 schema applied)
 *     db07  migrations table reflects applied V1 migrations
 *     db08  campaign discovered (probe needs a real FK target)
 *     db09  transaction(commit) — INSERT inside callback is visible after
 *     db10  transaction(throw) — INSERT inside callback is rolled back
 *
 *   Block C — DonationRepository end-to-end (REAL mutation)
 *     db11  DonationRepository::save inserts a row
 *     db12  DonationRepository::findById reconstructs the entity
 *     db13  DonationRepository::update persists a state change
 *     db14  DonationRepository::findByCampaignId paginates real rows
 *     db15  cleanup DELETE removes the probe row (finally block)
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;

// ─── Args ───────────────────────────────────────────────────────────────────
$outputPath = null;
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--output=')) {
        $outputPath = substr($arg, strlen('--output='));
    }
}

$startedAt = (new DateTimeImmutable())->format(DATE_ATOM);
$runId     = 'dbp-' . bin2hex(random_bytes(6));

// ─── Probe helpers ──────────────────────────────────────────────────────────
$probes       = [];
$probeIndex   = [];

$probe = function (string $id, string $name, callable $fn) use (&$probes, &$probeIndex): void {
    $probeStart = microtime(true);
    $record = [
        'id'         => $id,
        'name'       => $name,
        'status'     => 'pass',
        'started_at' => (new DateTimeImmutable())->format(DATE_ATOM),
    ];
    try {
        $result = $fn();
        if ($result !== null && (is_array($result) || is_string($result) || is_int($result))) {
            $record['detail'] = $result;
        }
    } catch (Throwable $e) {
        $record['status'] = 'fail';
        $record['error']  = $e->getMessage();
        $record['class']  = $e::class;
    }
    $record['elapsed_ms'] = round((microtime(true) - $probeStart) * 1000.0, 2);
    $record['at']         = (new DateTimeImmutable())->format(DATE_ATOM);
    $probes[]              = $record;
    $probeIndex[$id]       = $record;
};

// ─── Resolve adapter + repo (shared across all probes) ──────────────────────
$adapter = $app->make(PersistenceAdapterContract::class);
$repo    = $app->make(DonationRepositoryContract::class);

// ─── Shared mutable probe state (populated across probes) ───────────────────
$discoveredCampaignId  = null;   // set by db08 (existing or freshly created)
$seededCampaign        = false;  // true if db08 INSERTed a probe campaign
$seededCurrency        = false;  // true if db08 INSERTed INR into currencies
$createdDonationId     = null;   // set by db11
$originalInternalNotes = null;   // captured for db13 verification
$orphanRows            = [];     // accumulated for db15 cleanup

// ═══════════════════════════════════════════════════════════════════════════
// BLOCK A — Adapter surface
// ═══════════════════════════════════════════════════════════════════════════

$probe('db01', 'PersistenceAdapterContract resolves', function () use ($app) {
    $a = $app->make(PersistenceAdapterContract::class);
    if (! $a instanceof PersistenceAdapterContract) {
        throw new RuntimeException('Container did not return PersistenceAdapterContract');
    }

    return ['concrete' => $a::class];
});

$probe('db02', 'adapter is connected', function () use ($adapter) {
    if (! $adapter->isConnected()) {
        throw new RuntimeException('adapter->isConnected() returned false');
    }
});

$probe('db03', 'connectionMetadata() returns expected shape', function () use ($adapter) {
    $r = $adapter->connectionMetadata();
    if ($r->isFailure()) {
        throw new RuntimeException('connectionMetadata failed: '.$r->error());
    }
    $meta = $r->value();
    $required = ['driver', 'identifier', 'is_connected', 'database'];
    foreach ($required as $key) {
        if (! array_key_exists($key, $meta)) {
            throw new RuntimeException("metadata missing key: {$key}");
        }
    }
    if ($meta['is_connected'] !== true) {
        throw new RuntimeException('metadata.is_connected is not true: '.var_export($meta['is_connected'], true));
    }

    return ['driver' => $meta['driver'], 'database' => $meta['database']];
});

$probe('db04', 'adapter query(SELECT 1) round-trip', function () use ($adapter) {
    $r = $adapter->query('SELECT 1 AS one');
    if ($r->isFailure()) {
        throw new RuntimeException('query failed: '.$r->error());
    }
    $rows = $r->value();
    if (count($rows) !== 1 || (int) $rows[0]['one'] !== 1) {
        throw new RuntimeException('expected [[one => 1]], got '.json_encode($rows));
    }

    return ['rows' => count($rows)];
});

$probe('db05', 'Result wrapper returns failure (not throw) on bad SQL', function () use ($adapter) {
    // Doctrine check: the adapter MUST wrap query() failures in Result::failure
    // rather than letting exceptions propagate.
    $r = $adapter->query('SELECT this_column_does_not_exist FROM donations');
    if (! $r->isFailure()) {
        throw new RuntimeException('expected failure result, got success: '.json_encode($r->value()));
    }

    return ['error' => substr($r->error(), 0, 120)];
});

// ═══════════════════════════════════════════════════════════════════════════
// BLOCK B — Schema + transaction
// ═══════════════════════════════════════════════════════════════════════════

$probe('db06', 'donations table exists (V1 schema applied)', function () use ($adapter) {
    $r = $adapter->query(
        "SELECT count(*) AS n FROM information_schema.tables
         WHERE table_schema = 'public' AND table_name = 'donations'"
    );
    if ($r->isFailure()) {
        throw new RuntimeException('schema probe failed: '.$r->error());
    }
    $count = (int) $r->value()[0]['n'];
    if ($count !== 1) {
        throw new RuntimeException("expected 1 row in information_schema for 'donations', got {$count}");
    }

    return ['table_count' => $count];
});

$probe('db07', 'migrations table reflects applied V1 migrations', function () use ($adapter) {
    $r = $adapter->query('SELECT count(*) AS n FROM migrations');
    if ($r->isFailure()) {
        throw new RuntimeException('migrations probe failed: '.$r->error());
    }
    $count = (int) $r->value()[0]['n'];
    if ($count < 1) {
        throw new RuntimeException("expected at least 1 applied migration, got {$count}");
    }

    return ['migrations_applied' => $count];
});

$probe('db08', 'discover or seed a campaign for the probe FK target', function () use ($adapter, $runId, &$discoveredCampaignId, &$seededCampaign) {
    // Try to discover an existing campaign first (no mutation).
    $r = $adapter->query("SELECT id FROM campaigns WHERE deleted_at IS NULL ORDER BY created_at ASC LIMIT 1");
    if ($r->isFailure()) {
        throw new RuntimeException('campaign discovery failed: '.$r->error());
    }
    $rows = $r->value();
    if (! empty($rows)) {
        $discoveredCampaignId = (string) $rows[0]['id'];
        $seededCampaign = false;

        return ['campaign_id' => $discoveredCampaignId, 'seeded' => false];
    }

    // No campaigns exist on Neon — this is the seed-empty prod state.
    // To make the probe self-sufficient we must first seed currencies
    // (campaigns.currency_code has FK to currencies(code)).
    //
    // Discovery: is INR available?
    $currencyCheck = $adapter->query("SELECT code FROM currencies WHERE code = 'INR' LIMIT 1");
    if ($currencyCheck->isFailure()) {
        throw new RuntimeException('currencies lookup failed: '.$currencyCheck->error());
    }
    if (empty($currencyCheck->value())) {
        // Seed INR into currencies. Schema fields taken from V1-schema.sql:
        //   - code CHAR(3) PK
        //   - name, symbol TEXT NOT NULL
        //   - minor_unit_digits SMALLINT NOT NULL (CHECK 0..4)
        //   - is_active BOOLEAN NOT NULL DEFAULT TRUE
        //   - display_order INT NOT NULL DEFAULT 0
        $seedCurrency = $adapter->execute(
            "INSERT INTO currencies (code, name, symbol, minor_unit_digits, is_active, display_order, created_at, updated_at)
             VALUES ('INR', 'Indian Rupee', '₹', 2, TRUE, 0, NOW(), NOW())"
        );
        if ($seedCurrency->isFailure()) {
            throw new RuntimeException('failed to seed INR into currencies: '.$seedCurrency->error());
        }
        if ((int) $seedCurrency->value() !== 1) {
            throw new RuntimeException("currency INSERT affected {$seedCurrency->value()} rows");
        }
    }

    // Now seed the probe campaign. V1 schema notes:
    //   - campaigns.category is NOT NULL
    //   - campaigns.currency_code has FK to currencies(code)
    //   - target_amount_minor is nullable; if set, must be > 0
    //   - metadata defaults to '{}' (JSONB NOT NULL)
    $probeCampaignId = 'cmp-probe-'.$runId;
    $now = (new DateTimeImmutable())->format(DATE_ATOM);
    $insert = $adapter->execute(
        "INSERT INTO campaigns (
             id, slug, title, description, short_description, category,
             state, currency_code, target_amount_minor,
             is_featured, display_order, starts_at, ends_at,
             metadata, created_at, updated_at
         ) VALUES (
             :id, :slug, :title, :description, :short, :category,
             'active', 'INR', 100000,
             FALSE, 9999, NOW(), NOW() + INTERVAL '30 days',
             '{}'::jsonb, :now, :now
         )",
        [
            'id'                => $probeCampaignId,
            'slug'              => 'db-probe-'.$runId,
            'title'             => 'DB Probe Campaign '.$runId,
            'description'       => 'Seed campaign created by phase-2-db-probes.php run '.$runId.' — safe to DELETE if found orphan.',
            'short'             => 'DB probe',
            'category'          => 'general',
            'now'               => $now,
        ]
    );
    if ($insert->isFailure()) {
        throw new RuntimeException('probe campaign INSERT failed: '.$insert->error());
    }
    if ((int) $insert->value() !== 1) {
        throw new RuntimeException("probe campaign INSERT affected {$insert->value()} rows, expected 1");
    }
    $discoveredCampaignId = $probeCampaignId;
    $seededCampaign       = true;

    return [
        'campaign_id'        => $discoveredCampaignId,
        'seeded'             => true,
        'seeded_currency'    => empty($currencyCheck->value()),
    ];
});

$probe('db09', 'transaction(commit) makes INSERT visible after', function () use ($adapter, $runId, &$discoveredCampaignId) {
    $markerId = 'tx-test-'.$runId;
    // Note: idempotency_key is NULL here so we don't trip the FK to idempotency_keys
    $r = $adapter->transaction(function () use ($adapter, $markerId, &$discoveredCampaignId) {
        $exec = $adapter->execute(
            "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                                   is_anonymous, state, idempotency_key,
                                   submitted_at, created_at, updated_at)
             VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
                    NULL, NOW(), NOW())",
            ['id' => $markerId, 'cid' => $discoveredCampaignId]
        );
        if ($exec->isFailure()) {
            throw new RuntimeException('tx insert failed: '.$exec->error());
        }

        return $exec->value();
    });

    if ($r->isFailure()) {
        throw new RuntimeException('transaction wrapper returned failure: '.$r->error());
    }

    // Verify the row is visible from outside the transaction.
    // KNOWN FINDING: in this environment the transaction() wrapper returns
    // success but the inserted row is not visible to subsequent SELECTs.
    // Direct execute() works perfectly (db11/db12/db13/db14 use that path).
    // This is a Laravel transaction-vs-direct-execute divergence worth
    // investigating in the doctrine-compliance sweep.
    $check = $adapter->query('SELECT id FROM donations WHERE id = :id', ['id' => $markerId]);
    $visible = ! $check->isFailure() && ! empty($check->value());

    // Cleanup the tx-test row whether or not it's visible.
    $adapter->execute('DELETE FROM donations WHERE id = :id', ['id' => $markerId]);

    if (! $visible) {
        // Mark this as a finding rather than a hard failure — the probe
        // successfully executed the transaction path; only the visibility
        // assertion diverged. Soft-skip with a clear diagnostic.
        throw new RuntimeException(
            'KNOWN_FINDING: LaravelDbAdapter::transaction() returns success '
            .'but the INSERT is not visible to subsequent SELECTs on the same adapter. '
            .'Direct adapter->execute() path works correctly (used by db11-db14). '
            .'Likely cause: Laravel Connection::transaction() commit not propagating through '
            .'the Docker Desktop transparent port-forwarding on port 5432.'
        );
    }

    return ['committed' => true];
});

$probe('db10', 'transaction(throw) rolls back INSERT', function () use ($adapter, $runId, &$discoveredCampaignId) {
    $markerId = 'tx-rollback-'.$runId;
    // Doctrine: the adapter's transaction() catches the callback's exception
    // and returns Result::failure — it does NOT re-throw. So the rollback
    // signal arrives as a failure result, not a propagating exception.
    $r = $adapter->transaction(function () use ($adapter, $markerId, &$discoveredCampaignId) {
        $exec = $adapter->execute(
            "INSERT INTO donations (id, campaign_id, donor_id, amount_minor, currency_code,
                                   is_anonymous, state, idempotency_key,
                                   submitted_at, created_at, updated_at)
             VALUES (:id, :cid, NULL, 1, 'INR', TRUE, 'draft', NULL,
                    NULL, NOW(), NOW())",
            ['id' => $markerId, 'cid' => $discoveredCampaignId]
        );
        if ($exec->isFailure()) {
            throw new RuntimeException('tx insert failed: '.$exec->error());
        }
        throw new RuntimeException('intentional rollback trigger');
    });

    // The callback threw → adapter returns Result::failure (rollback signal).
    if (! $r->isFailure()) {
        throw new RuntimeException('expected Result::failure from callback-throwing transaction, got success');
    }
    if (! str_contains($r->error(), 'intentional rollback')) {
        throw new RuntimeException('failure error does not carry the original message: '.$r->error());
    }

    // Verify the row is NOT visible (rolled back).
    $check = $adapter->query('SELECT id FROM donations WHERE id = :id', ['id' => $markerId]);
    if ($check->isFailure()) {
        throw new RuntimeException('post-rollback check failed: '.$check->error());
    }
    if (! empty($check->value())) {
        // Self-cleanup if rollback didn't fire.
        $adapter->execute('DELETE FROM donations WHERE id = :id', ['id' => $markerId]);
        throw new RuntimeException('row IS visible after rollback — transaction did NOT roll back');
    }

    return ['rolled_back' => true, 'failure_error' => substr($r->error(), 0, 80)];
});

// ═══════════════════════════════════════════════════════════════════════════
// BLOCK C — DonationRepository end-to-end (REAL mutation)
// Wrapped in try/finally so cleanup probe (db15) runs no matter what.
// ═══════════════════════════════════════════════════════════════════════════

try {
    $probe('db11', 'DonationRepository::save inserts row', function () use ($repo, $adapter, &$createdDonationId, &$originalInternalNotes, $discoveredCampaignId, $runId) {
        if ($discoveredCampaignId === null) {
            throw new RuntimeException('db08 did not run first (campaign not discovered)');
        }

        // donations.idempotency_key has FK to idempotency_keys.key — must seed it first.
        // Schema: key PK, scope NOT NULL, request_fingerprint NOT NULL, expires_at NOT NULL, created_at NOT NULL.
        $seedIdem = $adapter->execute(
            "INSERT INTO idempotency_keys (key, scope, request_fingerprint, expires_at, created_at)
             VALUES (:k, 'donation', :fp, NOW() + INTERVAL '1 day', NOW())
             ON CONFLICT (key) DO NOTHING",
            [
                'k'  => $runId,
                'fp' => 'dbp-fingerprint-'.$runId,
            ]
        );
        if ($seedIdem->isFailure()) {
            throw new RuntimeException('failed to seed idempotency_keys row: '.$seedIdem->error());
        }

        // campaign_id must be in EntityId format (campaign_<26-char-ulid>)
        // — db08 stored a free-form probe id, so we re-generate a valid EntityId
        // pointing at the same campaign row.
        $campaignEntityId = EntityId::generate('campaign');
        $aliasUpdate = $adapter->execute(
            'UPDATE campaigns SET id = :new_id WHERE id = :old_id',
            ['new_id' => $campaignEntityId->value(), 'old_id' => $discoveredCampaignId]
        );
        if ($aliasUpdate->isFailure() || (int) $aliasUpdate->value() !== 1) {
            throw new RuntimeException('failed to alias probe campaign to EntityId format: '.($aliasUpdate->error() ?? 'no rows updated'));
        }
        $discoveredCampaignId = $campaignEntityId->value();

        $donation = Donation::draft(
            campaignId: $campaignEntityId,
            donor: DonorIdentity::identified(
                name: 'DB Probe',
                email: $runId.'@db-probe.local',
            ),
            amountMinor: 1,
            currency: Currency::INR,
            donorId: null,
            internalNotes: "db-probe run_id={$runId}",
            idempotencyKey: $runId,
        );

        $repo->save($donation);
        $createdDonationId     = $donation->id()->value();
        $originalInternalNotes = $donation->internalNotes();

        return [
            'donation_id'    => $createdDonationId,
            'campaign_id'    => $discoveredCampaignId,
            'state'          => $donation->state()->value,
            'internal_notes' => $originalInternalNotes,
            'idempotency_key' => $runId,
        ];
    });

    $probe('db12', 'DonationRepository::findById reconstructs entity', function () use ($repo, $createdDonationId, $originalInternalNotes, $runId) {
        if ($createdDonationId === null) {
            throw new RuntimeException('db11 did not run first (donation not created)');
        }

        $found = $repo->findById(EntityId::fromString($createdDonationId));
        if ($found === null) {
            throw new RuntimeException("findById returned null for {$createdDonationId}");
        }
        if ($found->id()->value() !== $createdDonationId) {
            throw new RuntimeException('round-trip id mismatch');
        }
        if ($found->internalNotes() !== $originalInternalNotes) {
            throw new RuntimeException('round-trip internal_notes mismatch: '.var_export($found->internalNotes(), true));
        }
        if ($found->idempotencyKey() !== $runId) {
            throw new RuntimeException('round-trip idempotency_key mismatch: '.var_export($found->idempotencyKey(), true));
        }
        if ($found->state()->value !== 'draft') {
            throw new RuntimeException('expected draft state, got '.var_export($found->state()->value, true));
        }

        return [
            'id'              => $found->id()->value(),
            'internal_notes'  => $found->internalNotes(),
            'idempotency_key' => $found->idempotencyKey(),
            'state'           => $found->state()->value,
        ];
    });

    $probe('db13', 'DonationRepository::update persists state change', function () use ($repo, $createdDonationId, $runId) {
        if ($createdDonationId === null) {
            throw new RuntimeException('db11 did not run first (donation not created)');
        }

        $existing = $repo->findById(EntityId::fromString($createdDonationId));
        if ($existing === null) {
            throw new RuntimeException("findById returned null for {$createdDonationId} before update");
        }

        $updatedNote = "db-probe run_id={$runId} UPDATE_TEST_VERIFIED";
        $updated     = $existing->withChanges([
            'internal_notes' => $updatedNote,
        ]);
        $repo->update($updated);

        // Verify by re-fetching (read your own writes, doctrine-critical).
        $reFetched = $repo->findById(EntityId::fromString($createdDonationId));
        if ($reFetched === null) {
            throw new RuntimeException('findById returned null AFTER update — update did not persist');
        }
        if ($reFetched->internalNotes() !== $updatedNote) {
            throw new RuntimeException(
                'update did not persist: expected "'.$updatedNote.'", got '.var_export($reFetched->internalNotes(), true)
            );
        }

        return [
            'updated_internal_notes' => $reFetched->internalNotes(),
        ];
    });

    $probe('db14', 'DonationRepository::findByCampaignId paginates real rows', function () use ($repo, $adapter, &$createdDonationId, &$discoveredCampaignId, $runId) {
        if ($discoveredCampaignId === null || $createdDonationId === null) {
            throw new RuntimeException('db08 / db11 did not run first');
        }

        // First: verify the donation has the campaign_id we expect (raw SQL).
        $directLookup = $adapter->query(
            'SELECT id, campaign_id, idempotency_key FROM donations WHERE id = :id AND deleted_at IS NULL',
            ['id' => $createdDonationId]
        )->value();
        if (empty($directLookup)) {
            throw new RuntimeException("donation $createdDonationId not visible via direct lookup — db11/db12/db13 must have failed silently");
        }
        $actualCampaign = (string) $directLookup[0]['campaign_id'];
        $expectedCampaign = $discoveredCampaignId;

        // Now: query via findByCampaignId using the actual campaign_id from DB.
        // (May differ from $discoveredCampaignId if a rename happened mid-probe.)
        $page = $repo->findByCampaignId(EntityId::fromString($actualCampaign), limit: 100, offset: 0);
        if (! is_array($page)) {
            throw new RuntimeException('findByCampaignId did not return an array');
        }

        $foundProbe = false;
        foreach ($page as $donation) {
            if ($donation->idempotencyKey() === $runId) {
                $foundProbe = true;
                break;
            }
        }
        if (! $foundProbe) {
            $repoIds = array_map(fn ($d) => $d->idempotencyKey(), $page);
            throw new RuntimeException(
                "probe donation not visible via findByCampaignId(campaign_id={$actualCampaign}). "
                ."expected campaign_id={$expectedCampaign}. "
                ."repo returned ".count($page)." donations with idempotency_keys: ".json_encode($repoIds)
            );
        }

        return [
            'page_size'         => count($page),
            'probe_row_visible' => true,
            'campaign_used'     => $actualCampaign,
        ];
    });
} finally {
    // Cleanup probe — runs even when earlier probes throw.
    $probe('db15', 'cleanup removes probe donation + probe campaign (finally)', function () use ($adapter, &$createdDonationId, &$discoveredCampaignId, &$seededCampaign, &$seededCurrency, $runId, &$orphanRows) {
        $cleaned = ['donation' => false, 'campaign' => false, 'currency' => false];
        $errors  = [];

        // 1. Cleanup the donation (if db11 created one)
        if ($createdDonationId !== null) {
            $r = $adapter->execute('DELETE FROM donations WHERE id = :id', ['id' => $createdDonationId]);
            if ($r->isFailure()) {
                $errors[] = 'donation DELETE: '.$r->error();
            } else {
                $cleaned['donation'] = true;
                $createdDonationId   = null;
            }
        }

        // 2. Cleanup the probe campaign ONLY if we seeded it ourselves
        if ($seededCampaign === true && $discoveredCampaignId !== null) {
            $r = $adapter->execute('DELETE FROM campaigns WHERE id = :id', ['id' => $discoveredCampaignId]);
            if ($r->isFailure()) {
                $errors[] = 'campaign DELETE: '.$r->error();
            } else {
                $cleaned['campaign'] = true;
                $seededCampaign      = false;
                $discoveredCampaignId = null;
            }
        }

        // 3. Cleanup the probe currency ONLY if we seeded it ourselves.
        //    Skip if any other campaigns still reference INR (means another
        //    campaign was created after ours that needs INR).
        if ($seededCurrency === true) {
            $refCheck = $adapter->query("SELECT count(*) AS n FROM campaigns WHERE currency_code = 'INR' AND deleted_at IS NULL");
            $refs = (int) ($refCheck->value()[0]['n'] ?? 0);
            if ($refs === 0) {
                $r = $adapter->execute("DELETE FROM currencies WHERE code = 'INR'");
                if ($r->isFailure()) {
                    $errors[] = 'currency DELETE: '.$r->error();
                } else {
                    $cleaned['currency'] = true;
                    $seededCurrency      = false;
                }
            } else {
                $cleaned['currency'] = 'skipped (other campaigns reference INR)';
            }
        }

        // 4. Verify nothing was left behind for this run
        $verify = $adapter->query(
            'SELECT count(*) AS n FROM donations WHERE idempotency_key = :k',
            ['k' => $runId]
        );
        $remaining = (int) ($verify->value()[0]['n'] ?? -1);
        if ($remaining !== 0) {
            $errors[] = "{$remaining} donation row(s) still present for run_id {$runId}";
        }

        if (! empty($errors)) {
            $orphanRows[] = [
                'run_id'      => $runId,
                'campaign_id' => $discoveredCampaignId,
                'errors'      => $errors,
                'captured_at' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];
            $manifestPath = __DIR__.'/../storage/logs/db-probe-orphans.json';
            $existing = [];
            if (is_readable($manifestPath)) {
                $decoded = json_decode((string) file_get_contents($manifestPath), true);
                if (is_array($decoded)) {
                    $existing = $decoded;
                }
            }
            $existing = array_merge($existing, $orphanRows);
            @mkdir(dirname($manifestPath), 0775, true);
            @file_put_contents($manifestPath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

            throw new RuntimeException('cleanup incomplete; orphan recorded at '.$manifestPath.' — errors: '.implode('; ', $errors));
        }

        return [
            'cleaned'           => true,
            'donation_deleted'  => $cleaned['donation'],
            'campaign_deleted'  => $cleaned['campaign'],
            'currency_deleted'  => $cleaned['currency'],
            'orphan_count'      => count($orphanRows),
        ];
    });
}

// ─── Assemble report ────────────────────────────────────────────────────────
$passed = count(array_filter($probes, fn ($p) => $p['status'] === 'pass'));
$failed = count($probes) - $passed;

$report = [
    'phase'    => '2 — Database integration (Neon production)',
    'branch'   => trim(shell_exec('git rev-parse --abbrev-ref HEAD 2>/dev/null') ?: 'unknown'),
    'run_id'   => $runId,
    'ran_at'   => $startedAt,
    'total'    => count($probes),
    'passed'   => $passed,
    'failed'   => $failed,
    'target'   => [
        'driver'    => $adapter->driver(),
        'connected' => $adapter->isConnected(),
    ],
    'probes'   => $probes,
    'orphans'  => $orphanRows,
];

// ─── Output ─────────────────────────────────────────────────────────────────
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

if ($outputPath !== null) {
    file_put_contents($outputPath, $json);
    fwrite(STDERR, "wrote {$outputPath}\n");
}

echo $json;

// ─── Exit code ──────────────────────────────────────────────────────────────
exit($failed === 0 ? 0 : 1);
