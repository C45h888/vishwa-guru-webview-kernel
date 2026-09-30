<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Donor;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\DonorRepositoryContract;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Persistence\Contracts\PersistenceAdapterContract;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * One-off, rerunnable projection of verified identified donations into the
 * donor CRM table. This is deliberately separate from ProductionSeeder:
 * ordinary content reseeds must never synthesize or rewrite donor PII.
 *
 * Run only after the donor CRM schema migration:
 *   php artisan db:seed --class=Database\\Seeders\\ProductionDonorProjectionSeeder --force
 *
 * The whole projection, including CRM rollups, runs in one transaction. A
 * failed row therefore cannot leave a donor profile or donation link behind.
 */
final class ProductionDonorProjectionSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('production')) {
            $this->command->error('[ProductionDonorProjectionSeeder] Refusing to run outside APP_ENV=production.');

            return;
        }

        $adapter = $this->container->make(PersistenceAdapterContract::class);
        $donations = $this->container->make(DonationRepositoryContract::class);
        $donors = $this->container->make(DonorRepositoryContract::class);

        $transaction = $adapter->transaction(function () use ($adapter, $donations, $donors): array {
            $result = $adapter->query(<<<'SQL'
SELECT d.*
FROM donations d
WHERE d.deleted_at IS NULL
  AND d.is_anonymous = FALSE
  AND d.donor_id IS NULL
  AND d.state IN ('payment_verified', 'receipt_generated', 'completed')
  AND TRIM(COALESCE(d.donor_name_snapshot, '')) <> ''
  AND (
      NULLIF(TRIM(COALESCE(d.donor_email_snapshot, '')), '') IS NOT NULL
      OR NULLIF(TRIM(COALESCE(d.donor_phone_snapshot, '')), '') IS NOT NULL
  )
  AND EXISTS (
      SELECT 1
      FROM payments p
      WHERE p.donation_id = d.id
        AND p.status IN ('captured', 'settling', 'settled')
        AND p.deleted_at IS NULL
  )
ORDER BY d.created_at ASC, d.id ASC
FOR UPDATE
SQL);

            if ($result->isFailure()) {
                throw new RuntimeException('Source query failed: '.$result->error());
            }

            $created = 0;
            $linked = 0;
            $updated = 0;

            foreach ($result->value() as $row) {
                // The SQL predicate is the first line of defence. Keep this
                // check because this command handles donor PII and must never
                // turn an anonymous contribution into a CRM identity.
                if (filter_var($row['is_anonymous'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $donation = Donation::fromRow($row);
                if ($donation->donorId() !== null || ! $donation->state()->isSuccessful()) {
                    continue;
                }

                $identity = self::identityFromDonation($donation);
                $donor = self::findUnambiguousMatch($donors, $identity);

                if ($donor === null) {
                    $donor = Donor::identified(
                        name: (string) $identity->name(),
                        email: $identity->email(),
                        phone: $identity->phone(),
                        panNumber: $identity->pan(),
                        address: $identity->address(),
                        preferredCurrency: $donation->currency(),
                    );
                    $donors->save($donor);
                    $created++;
                } else {
                    // Fill missing CRM fields from the immutable donation
                    // snapshot, but do not overwrite an operator-maintained
                    // value with an older or less complete snapshot.
                    $changes = self::profileChanges($donor, $identity);
                    if ($changes !== []) {
                        $donor = $donor->withChanges($changes);
                        $donors->update($donor);
                        $updated++;
                    }
                }

                $donations->update($donation->withChanges(['donor_id' => $donor->id()]));
                $linked++;
            }

            self::rebuildRollups($adapter);

            return [
                'created' => $created,
                'linked' => $linked,
                'updated' => $updated,
            ];
        });

        if ($transaction->isFailure()) {
            $this->command->error('[ProductionDonorProjectionSeeder] Rolled back: '.$transaction->error());

            return;
        }

        $outcome = $transaction->value();
        $this->command->info(sprintf(
            '[ProductionDonorProjectionSeeder] Complete: %d donor profiles created, %d existing profiles enriched, %d donations linked. Rollups rebuilt. Anonymous and unpaid donations were excluded.',
            $outcome['created'],
            $outcome['updated'],
            $outcome['linked'],
        ));
    }

    private static function identityFromDonation(Donation $donation): DonorIdentity
    {
        $email = self::nullableTrim($donation->donorEmailSnapshot());
        $phone = self::nullableTrim($donation->donorPhoneSnapshot());
        $pan = self::nullableTrim($donation->donorPanSnapshot());
        $pan = $pan === null ? null : strtoupper($pan);

        // Old/imported donations may contain a malformed PAN. PAN is
        // optional for CRM projection; dropping only the invalid PAN lets a
        // verified donation be linked without weakening Donor validation.
        if ($pan !== null && preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan) !== 1) {
            $pan = null;
        }

        return DonorIdentity::identified(
            name: trim((string) $donation->donorNameSnapshot()),
            email: $email,
            phone: $phone,
            pan: $pan,
            address: self::normaliseAddress($donation->donorAddressSnapshot()),
        );
    }

    private static function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * Match contacts independently so a donation cannot silently join the
     * older of two different donor records when email and phone disagree.
     */
    private static function findUnambiguousMatch(
        DonorRepositoryContract $donors,
        DonorIdentity $identity,
    ): ?Donor {
        $byEmail = $identity->email() === null
            ? null
            : $donors->findByEmail($identity->email());
        $byPhone = $identity->phone() === null
            ? null
            : $donors->findByPhone($identity->phone());

        if ($byEmail !== null && $byPhone !== null && $byEmail->id()->value() !== $byPhone->id()->value()) {
            throw new RuntimeException(sprintf(
                'Ambiguous donor match for %s: email and phone belong to different profiles.',
                $identity->name(),
            ));
        }

        return $byEmail ?? $byPhone;
    }

    /**
     * @return array<string, mixed>
     */
    private static function profileChanges(Donor $donor, DonorIdentity $identity): array
    {
        $changes = [];

        if (trim($donor->name()) === '' && $identity->name() !== null) {
            $changes['name'] = $identity->name();
        }
        if ($donor->email() === null && $identity->email() !== null) {
            $changes['email'] = $identity->email();
        }
        if ($donor->phone() === null && $identity->phone() !== null) {
            $changes['phone'] = $identity->phone();
        }
        if ($donor->panNumber() === null && $identity->pan() !== null) {
            $changes['pan_number'] = $identity->pan();
        }

        $incomingAddress = $identity->address() ?? [];
        if ($incomingAddress !== []) {
            $mergedAddress = array_merge($donor->address() ?? [], $incomingAddress);
            if ($mergedAddress !== ($donor->address() ?? [])) {
                $changes['address'] = $mergedAddress;
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, string>|null  $address
     * @return array<string, string>|null
     */
    private static function normaliseAddress(?array $address): ?array
    {
        if ($address === null) {
            return null;
        }

        $normalised = [];
        foreach ($address as $key => $value) {
            $value = trim($value);
            if ($value !== '') {
                $normalised[(string) $key] = $value;
            }
        }

        return $normalised === [] ? null : $normalised;
    }

    private static function rebuildRollups(PersistenceAdapterContract $adapter): void
    {
        // lifetime_contribution_minor is a scalar in the current schema, so
        // it is only meaningful in the donor's preferred currency. Counts and
        // dates include every qualifying currency; the sum excludes a
        // different currency rather than adding incomparable minor units.
        $rollup = $adapter->execute(<<<'SQL'
UPDATE donors AS donor
SET donation_count = rollup.donation_count,
    first_donation_at = rollup.first_donation_at,
    last_donation_at = rollup.last_donation_at,
    lifetime_contribution_minor = rollup.lifetime_contribution_minor
FROM (
    SELECT d.donor_id,
           COUNT(*)::integer AS donation_count,
           MIN(d.created_at) AS first_donation_at,
           MAX(d.created_at) AS last_donation_at,
           COALESCE(SUM(
               CASE WHEN d.currency_code = donor_profile.preferred_currency
                    THEN COALESCE(p.amount_captured_minor, p.amount_minor, d.amount_minor)
                    ELSE 0
               END
           ), 0)::bigint AS lifetime_contribution_minor
    FROM donations d
    INNER JOIN payments p ON p.donation_id = d.id
    INNER JOIN donors donor_profile ON donor_profile.id = d.donor_id
    WHERE d.deleted_at IS NULL
      AND d.donor_id IS NOT NULL
      AND d.is_anonymous = FALSE
      AND d.state IN ('payment_verified', 'receipt_generated', 'completed')
      AND p.deleted_at IS NULL
      AND p.status IN ('captured', 'settling', 'settled')
    GROUP BY d.donor_id
) AS rollup
WHERE donor.id = rollup.donor_id
  AND donor.deleted_at IS NULL
SQL);

        if ($rollup->isFailure()) {
            throw new RuntimeException('Donor rollup rebuild failed: '.$rollup->error());
        }

        $reset = $adapter->execute(<<<'SQL'
UPDATE donors AS donor
SET donation_count = 0,
    first_donation_at = NULL,
    last_donation_at = NULL,
    lifetime_contribution_minor = 0
WHERE donor.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM donations d
      INNER JOIN payments p ON p.donation_id = d.id
      WHERE d.donor_id = donor.id
        AND d.deleted_at IS NULL
        AND d.is_anonymous = FALSE
        AND d.state IN ('payment_verified', 'receipt_generated', 'completed')
        AND p.deleted_at IS NULL
        AND p.status IN ('captured', 'settling', 'settled')
  )
SQL);

        if ($reset->isFailure()) {
            throw new RuntimeException('Empty donor rollup reset failed: '.$reset->error());
        }
    }
}
