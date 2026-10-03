<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

/**
 * TrustIdentity — the canonical donee (trust) identity snapshot.
 *
 * Single-row aggregate (`trust_identities`, key `canonical`). Carries the
 * statutory credentials that every receipt surface must print:
 *
 *   - TAN  (tax deduction account number — required on receipts + 10BD)
 *   - PAN  (donee PAN — required on the 80G certificate block)
 *   - 80G registration number (ITD allotment, e.g. F.No.S-504/…)
 *   - 12A/12AA registration number (exemption registration)
 *
 * plus the presentation identity (name, address, email, phone).
 *
 * DB-only doctrine: the receipts pipeline reads this row via
 * TrustIdentityRepositoryContract (transported by DataWorker, typed by
 * TypesWorker). Statutory credentials (PAN, TAN, 80G + 12A numbers) have
 * NO env/config fallback anywhere — a missing row yields null, never a
 * fabricated value. Presentation strings (name/address/email/phone) are
 * DB-first with a substrate config fallback for unseeded environments.
 * The canonical seeded values live in the k_payments trust_identities
 * migrations, not in env files.
 */
final readonly class TrustIdentity
{
    public const CANONICAL_KEY = 'canonical';

    public function __construct(
        public string $key,
        public ?string $name,
        public ?string $address,
        public ?string $email,
        public ?string $phone,
        public ?string $pan,
        public ?string $tan,
        public ?string $eightyGNumber,
        public ?string $twelveANumber,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $str = static fn (string $k): ?string => isset($row[$k]) && $row[$k] !== '' && $row[$k] !== null
            ? (string) $row[$k]
            : null;

        // Column names are trust_*-free on the table (the table IS the
        // trust); accept both the bare column names and the legacy
        // trust_-prefixed aliases used by older exports.
        $pick = static function (string $bare, string $prefixed) use ($row, $str): ?string {
            return $str($bare) ?? $str($prefixed);
        };

        return new self(
            key: (string) ($row['key'] ?? self::CANONICAL_KEY),
            name: $pick('name', 'trust_name'),
            address: $pick('address', 'trust_address'),
            email: $pick('email', 'trust_email'),
            phone: $pick('phone', 'trust_phone'),
            pan: $pick('pan', 'trust_pan'),
            tan: $pick('tan', 'trust_tan'),
            eightyGNumber: $str('eighty_g_number') ?? $str('trust_80g_number') ?? $str('eightyGNumber'),
            twelveANumber: $str('twelve_a_number') ?? $str('trust_12a_number') ?? $str('twelveANumber'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'address' => $this->address,
            'email' => $this->email,
            'phone' => $this->phone,
            'pan' => $this->pan,
            'tan' => $this->tan,
            'eighty_g_number' => $this->eightyGNumber,
            'twelve_a_number' => $this->twelveANumber,
        ];
    }
}
