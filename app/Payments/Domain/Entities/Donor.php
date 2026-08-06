<?php

declare(strict_types=1);

namespace App\Payments\Domain\Entities;

use App\Payments\Domain\Enums\Currency;
use App\Persistence\Contracts\EntityContract;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Donor aggregate root.
 *
 * Mirrors the `donors` table defined in `schema-neon/V1-schema.sql`.
 * Donors are the deduplicated, persistent identity behind a donation.
 * Anonymous donations may carry donor_id=NULL — Donor is only created
 * for identified donors.
 */
final class Donor implements EntityContract
{
    public const ENTITY_TYPE = 'donor';

    /**
     * @param  array<string, string>|null  $address
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        private readonly EntityId $id,
        private readonly string $name,
        private readonly ?string $email,
        private readonly ?string $phone,
        private readonly ?string $panNumber,
        private readonly ?array $address,
        private readonly Currency $preferredCurrency,
        private readonly bool $isAnonymized,
        private readonly ?DateTimeImmutable $anonymizedAt,
        private readonly array $metadata,
        private readonly DateTimeImmutable $createdAt,
        private readonly DateTimeImmutable $updatedAt,
        private readonly ?DateTimeImmutable $deletedAt = null,
        private readonly ?DateTimeImmutable $firstDonationAt = null,
        private readonly ?DateTimeImmutable $lastDonationAt = null,
        private readonly int $donationCount = 0,
        private readonly int $lifetimeContributionMinor = 0,
    ) {
    }

    /**
     * @param  array<string, string>|null  $address
     * @param  array<string, mixed>  $metadata
     */
    public static function identified(
        string $name,
        ?string $email,
        ?string $phone,
        ?string $panNumber = null,
        ?array $address = null,
        Currency $preferredCurrency = Currency::INR,
        array $metadata = [],
        ?EntityId $id = null,
    ): self {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Donor name cannot be empty');
        }
        if ($email === null && $phone === null) {
            throw new InvalidArgumentException(
                'Donor must have at least one contact channel (email or phone)'
            );
        }
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                "Donor email is not a valid address: {$email}"
            );
        }
        if ($panNumber !== null && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $panNumber)) {
            throw new InvalidArgumentException(
                "Donor PAN format is invalid: {$panNumber}"
            );
        }

        $now = new DateTimeImmutable();

        return new self(
            id: $id ?? EntityId::generate(self::ENTITY_TYPE),
            name: $name,
            email: $email,
            phone: $phone,
            panNumber: $panNumber,
            address: $address,
            preferredCurrency: $preferredCurrency,
            isAnonymized: false,
            anonymizedAt: null,
            metadata: $metadata,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        // The DB schema column is `full_name`; the entity uses the
        // shorter `name` key in toArray(). Accept either so both
        // round-trips through the repository (which maps full_name↔name)
        // and direct fromRow() callers with the entity-shaped key work.
        $nameKey = array_key_exists('name', $row) ? 'name' : 'full_name';
        $required = ['id', $nameKey, 'preferred_currency', 'is_anonymized', 'created_at', 'updated_at'];
        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("Donor row missing required key: {$key}");
            }
        }

        return new self(
            id: EntityId::fromString($row['id']),
            name: (string) $row[$nameKey],
            email: $row['email'] ?? null,
            phone: $row['phone'] ?? null,
            panNumber: $row['pan_number'] ?? null,
            address: isset($row['address']) ? self::decodeJson($row['address']) : null,
            preferredCurrency: Currency::from($row['preferred_currency']),
            isAnonymized: (bool) $row['is_anonymized'],
            anonymizedAt: self::parseDate($row['anonymized_at'] ?? null),
            metadata: self::decodeJson($row['metadata'] ?? '{}'),
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
            updatedAt: self::parseDate($row['updated_at']) ?? new DateTimeImmutable(),
            deletedAt: self::parseDate($row['deleted_at'] ?? null),
            firstDonationAt: self::parseDate($row['first_donation_at'] ?? null),
            lastDonationAt: self::parseDate($row['last_donation_at'] ?? null),
            donationCount: (int) ($row['donation_count'] ?? 0),
            lifetimeContributionMinor: (int) ($row['lifetime_contribution_minor'] ?? 0),
        );
    }

    public function id(): EntityId
    {
        return $this->id;
    }

    public function entityType(): string
    {
        return self::ENTITY_TYPE;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->value(),
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'pan_number' => $this->panNumber,
            'address' => $this->address !== null
                ? json_encode($this->address, JSON_THROW_ON_ERROR)
                : null,
            'preferred_currency' => $this->preferredCurrency->value,
            'is_anonymized' => $this->isAnonymized,
            'anonymized_at' => $this->anonymizedAt?->format(DATE_ATOM),
            'metadata' => json_encode($this->metadata, JSON_THROW_ON_ERROR),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
            'first_donation_at' => $this->firstDonationAt?->format(DATE_ATOM),
            'last_donation_at' => $this->lastDonationAt?->format(DATE_ATOM),
            'donation_count' => $this->donationCount,
            'lifetime_contribution_minor' => $this->lifetimeContributionMinor,
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function withChanges(array $changes): static
    {
        $row = $this->toArray();
        $merged = array_merge($row, $changes);
        $merged['updated_at'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return self::fromRow($merged);
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function name(): string
    {
        return $this->name;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function panNumber(): ?string
    {
        return $this->panNumber;
    }

    /**
     * @return array<string, string>|null
     */
    public function address(): ?array
    {
        return $this->address;
    }

    public function preferredCurrency(): Currency
    {
        return $this->preferredCurrency;
    }

    public function isAnonymized(): bool
    {
        return $this->isAnonymized;
    }

    public function anonymizedAt(): ?DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function firstDonationAt(): ?DateTimeImmutable
    {
        return $this->firstDonationAt;
    }

    public function lastDonationAt(): ?DateTimeImmutable
    {
        return $this->lastDonationAt;
    }

    public function donationCount(): int
    {
        return $this->donationCount;
    }

    public function lifetimeContributionMinor(): int
    {
        return $this->lifetimeContributionMinor;
    }

    public function hasEmail(): bool
    {
        return $this->email !== null;
    }

    public function hasPhone(): bool
    {
        return $this->phone !== null;
    }

    public function hasPan(): bool
    {
        return $this->panNumber !== null;
    }

    public function identifier(): Identifier
    {
        return new Identifier($this->id->ulid());
    }

    /**
     * @param  array<string, mixed>|string  $value
     * @return array<string, mixed>
     */
    private static function decodeJson(array|string $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value);
    }
}