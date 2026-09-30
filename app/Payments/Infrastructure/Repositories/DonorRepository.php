<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Repositories;

use App\Payments\Domain\Entities\Donor;
use App\Payments\Domain\Repositories\DonorRepositoryContract;
use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\ValueObjects\EntityId;
use RuntimeException;

final class DonorRepository implements DonorRepositoryContract
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {}

    public function findById(EntityId $id): ?Donor
    {
        $result = $this->adapter->query(
            'SELECT * FROM donors WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id->value()],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return $this->toEntity($result->value()[0]);
    }

    public function findByEmail(string $email): ?Donor
    {
        $result = $this->adapter->query(
            'SELECT * FROM donors WHERE email = :email AND deleted_at IS NULL LIMIT 1',
            ['email' => $email],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return $this->toEntity($result->value()[0]);
    }

    public function findByPhone(string $phone): ?Donor
    {
        $result = $this->adapter->query(
            'SELECT * FROM donors WHERE phone = :phone AND deleted_at IS NULL LIMIT 1',
            ['phone' => $phone],
        );
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return $this->toEntity($result->value()[0]);
    }

    public function findByEmailOrPhone(?string $email, ?string $phone): ?Donor
    {
        if ($email === null && $phone === null) {
            return null;
        }

        $conditions = [];
        $params = [];

        if ($email !== null) {
            $conditions[] = 'email = :email';
            $params['email'] = $email;
        }
        if ($phone !== null) {
            $conditions[] = 'phone = :phone';
            $params['phone'] = $phone;
        }

        $sql = 'SELECT * FROM donors WHERE ('.implode(' OR ', $conditions).') AND deleted_at IS NULL ORDER BY created_at ASC, id ASC LIMIT 1';

        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure() || empty($result->value())) {
            return null;
        }

        return $this->toEntity($result->value()[0]);
    }

    public function save(Donor $donor): void
    {
        $row = $donor->toArray();
        $address = $this->addressColumns($row['address'] ?? null);

        $sql = 'INSERT INTO donors (
            id, full_name, email, phone, country_code,
            address_line_1, address_line_2, city, state_region, postal_code,
            pan_number, preferred_lang, preferred_currency, is_anonymized, anonymized_at,
            metadata, first_donation_at, last_donation_at, donation_count,
            lifetime_contribution_minor, notes,
            created_at, updated_at, deleted_at
        ) VALUES (
            :id, :full_name, :email, :phone, :country_code,
            :address_line_1, :address_line_2, :city, :state_region, :postal_code,
            :pan_number, :preferred_lang, :preferred_currency, :is_anonymized, :anonymized_at,
            :metadata, :first_donation_at, :last_donation_at, :donation_count,
            :lifetime_contribution_minor, :notes,
            :created_at, :updated_at, :deleted_at
        )';

        $params = [
            'id' => $row['id'],
            'full_name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            ...$address,
            'pan_number' => $row['pan_number'] ?? null,
            'preferred_lang' => $row['preferred_lang'] ?? null,
            'preferred_currency' => $row['preferred_currency'],
            'is_anonymized' => $row['is_anonymized'],
            'anonymized_at' => $row['anonymized_at'],
            'metadata' => $row['metadata'],
            'first_donation_at' => $row['first_donation_at'],
            'last_donation_at' => $row['last_donation_at'],
            'donation_count' => $row['donation_count'],
            'lifetime_contribution_minor' => $row['lifetime_contribution_minor'],
            'notes' => $row['notes'] ?? null,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'deleted_at' => $row['deleted_at'],
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('DonorRepository::save failed: '.$exec->error());
        }
    }

    public function update(Donor $donor): void
    {
        $row = $donor->toArray();
        $address = $this->addressColumns($row['address'] ?? null);

        $sql = 'UPDATE donors SET
            full_name = :full_name,
            email = :email,
            phone = :phone,
            country_code = :country_code,
            address_line_1 = :address_line_1,
            address_line_2 = :address_line_2,
            city = :city,
            state_region = :state_region,
            postal_code = :postal_code,
            pan_number = :pan_number,
            preferred_lang = :preferred_lang,
            preferred_currency = :preferred_currency,
            is_anonymized = :is_anonymized,
            anonymized_at = :anonymized_at,
            notes = :notes,
            metadata = :metadata,
            updated_at = :updated_at,
            first_donation_at = :first_donation_at,
            last_donation_at = :last_donation_at,
            donation_count = :donation_count,
            lifetime_contribution_minor = :lifetime_contribution_minor
        WHERE id = :id';

        $params = [
            'id' => $row['id'],
            'full_name' => $row['name'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            ...$address,
            'pan_number' => $row['pan_number'] ?? null,
            'preferred_lang' => $row['preferred_lang'] ?? null,
            'preferred_currency' => $row['preferred_currency'],
            'is_anonymized' => $row['is_anonymized'],
            'anonymized_at' => $row['anonymized_at'],
            'notes' => $row['notes'] ?? null,
            'metadata' => is_string($row['metadata'])
                ? $row['metadata']
                : json_encode($row['metadata'] ?? [], JSON_THROW_ON_ERROR),
            'updated_at' => $row['updated_at'],
            'first_donation_at' => $row['first_donation_at'],
            'last_donation_at' => $row['last_donation_at'],
            'donation_count' => $row['donation_count'] ?? 0,
            'lifetime_contribution_minor' => $row['lifetime_contribution_minor'] ?? 0,
        ];

        $exec = $this->adapter->execute($sql, $params);
        if ($exec->isFailure()) {
            throw new RuntimeException('DonorRepository::update failed: '.$exec->error());
        }
    }

    public function anonymize(EntityId $id): void
    {
        $updatedAt = (new \DateTimeImmutable())->format(DATE_ATOM);

        $sql = 'UPDATE donors SET
            is_anonymized = true,
            full_name = \'Anonymized donor\',
            email = NULL,
            phone = NULL,
            pan_number = NULL,
            country_code = NULL,
            address_line_1 = NULL,
            address_line_2 = NULL,
            city = NULL,
            state_region = NULL,
            postal_code = NULL,
            anonymized_at = :anonymized_at,
            metadata = \'{}\',
            notes = NULL,
            updated_at = :updated_at
        WHERE id = :id AND deleted_at IS NULL';

        $exec = $this->adapter->execute($sql, [
            'id' => $id->value(),
            'anonymized_at' => $updatedAt,
            'updated_at' => $updatedAt,
        ]);
        if ($exec->isFailure()) {
            throw new RuntimeException('DonorRepository::anonymize failed: '.$exec->error());
        }
    }

    public function existsWithEmail(string $email): bool
    {
        $result = $this->adapter->query(
            'SELECT 1 FROM donors WHERE email = :email AND deleted_at IS NULL LIMIT 1',
            ['email' => $email],
        );

        return ! $result->isFailure() && ! empty($result->value());
    }

    public function existsWithPhone(string $phone): bool
    {
        $result = $this->adapter->query(
            'SELECT 1 FROM donors WHERE phone = :phone AND deleted_at IS NULL LIMIT 1',
            ['phone' => $phone],
        );

        return ! $result->isFailure() && ! empty($result->value());
    }

    /** @param array<string, mixed> $row */
    private function toEntity(array $row): Donor
    {
        if (! array_key_exists('address', $row)) {
            $address = [
                'line1' => $row['address_line_1'] ?? null,
                'line2' => $row['address_line_2'] ?? null,
                'city' => $row['city'] ?? null,
                'state' => $row['state_region'] ?? null,
                'pincode' => $row['postal_code'] ?? null,
            ];
            $address = array_filter($address, static fn ($value): bool => $value !== null && $value !== '');
            if (($row['country_code'] ?? null) !== null && $row['country_code'] !== '') {
                $address['country_code'] = $row['country_code'];
            }
            $row['address'] = $address === [] ? null : json_encode($address, JSON_THROW_ON_ERROR);
        }

        return Donor::fromRow($row);
    }

    /** @return array<string, mixed> */
    private function addressColumns(mixed $address): array
    {
        if (is_string($address)) {
            $decoded = json_decode($address, true);
            $address = is_array($decoded) ? $decoded : [];
        }
        $address = is_array($address) ? $address : [];

        return [
            'country_code' => $address['country_code'] ?? null,
            'address_line_1' => $address['line1'] ?? $address['address_line_1'] ?? null,
            'address_line_2' => $address['line2'] ?? $address['address_line_2'] ?? null,
            'city' => $address['city'] ?? null,
            'state_region' => $address['state'] ?? $address['state_region'] ?? null,
            'postal_code' => $address['postal_code'] ?? $address['postalCode'] ?? $address['pincode'] ?? null,
        ];
    }
}
