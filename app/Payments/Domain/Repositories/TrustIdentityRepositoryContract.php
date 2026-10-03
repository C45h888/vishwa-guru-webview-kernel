<?php

declare(strict_types=1);

namespace App\Payments\Domain\Repositories;

use App\Payments\Domain\ValueObjects\TrustIdentity;

/**
 * Persistence boundary for the canonical trust (donee) identity.
 *
 * Single-row aggregate: `findCanonical()` returns the `canonical` row or
 * null when the trust_identities table has never been seeded. Callers
 * (DataWorker → TypesWorker) treat null as "fall back to
 * config/receipts.php env values" — the DB row is authoritative, env is
 * the fallback, never the reverse.
 */
interface TrustIdentityRepositoryContract
{
    public function findCanonical(): ?TrustIdentity;

    public function save(TrustIdentity $identity): void;
}
