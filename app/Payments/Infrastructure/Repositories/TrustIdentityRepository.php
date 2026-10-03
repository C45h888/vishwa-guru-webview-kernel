<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Repositories\TrustIdentityRepositoryContract;
use App\Payments\Domain\ValueObjects\TrustIdentity;
use App\Persistence\Contracts\PersistenceAdapterContract;

/**
 * Eloquent-less repository over `trust_identities` via the canonical
 * PersistenceAdapter (raw SQL, portable across Neon pgsql + sqlite test
 * mirror — same pattern as ReceiptRepository).
 */
final class TrustIdentityRepository implements TrustIdentityRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    public function findCanonical(): ?TrustIdentity
    {
        $result = $this->adapter->query(
            'SELECT * FROM trust_identities WHERE key = :key LIMIT 1',
            ['key' => TrustIdentity::CANONICAL_KEY],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return TrustIdentity::fromRow($result->value()[0]);
    }

    public function save(TrustIdentity $identity): void
    {
        $row = $identity->toArray();

        if ($this->findCanonical() !== null && $identity->key === TrustIdentity::CANONICAL_KEY) {
            $this->adapter->execute(
                'UPDATE trust_identities SET name = :name, address = :address, email = :email, phone = :phone, pan = :pan, tan = :tan, eighty_g_number = :eighty_g_number, twelve_a_number = :twelve_a_number, updated_at = :updated_at WHERE key = :key',
                [...$row, 'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM)],
            );

            return;
        }

        $this->adapter->execute(
            'INSERT INTO trust_identities (key, name, address, email, phone, pan, tan, eighty_g_number, twelve_a_number, created_at, updated_at) VALUES (:key, :name, :address, :email, :phone, :pan, :tan, :eighty_g_number, :twelve_a_number, :created_at, :updated_at)',
            [...$row, 'created_at' => (new \DateTimeImmutable())->format(DATE_ATOM), 'updated_at' => (new \DateTimeImmutable())->format(DATE_ATOM)],
        );
    }
}
