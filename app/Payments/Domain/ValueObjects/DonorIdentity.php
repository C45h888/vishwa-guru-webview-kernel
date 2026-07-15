<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * Donor identity captured at donation creation time.
 *
 * Two shapes are supported:
 *   - Identified: name + at least one contact channel (email or phone)
 *   - Anonymous: every PII field null, isAnonymous()=true
 *
 * The donations table CHECK constraint
 * (donations_anonymous_no_pii) enforces the anonymous contract at the
 * schema layer; this VO enforces it at the domain layer so the failure
 * surfaces BEFORE persistence.
 */
final class DonorIdentity
{
    /**
     * @param  array<string, string>|null  $address  Free-form address lines (street, city, state, postal, country)
     */
    public function __construct(
        private readonly ?string $name = null,
        private readonly ?string $email = null,
        private readonly ?string $phone = null,
        private readonly ?string $pan = null,
        private readonly ?array $address = null,
    ) {
        if ($name !== null && trim($name) === '') {
            throw new InvalidArgumentException('DonorIdentity name cannot be empty string');
        }
        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException(
                "DonorIdentity email is not a valid address: {$email}"
            );
        }
        if ($phone !== null && ! preg_match('/^\+?[0-9\s\-()]{7,20}$/', $phone)) {
            throw new InvalidArgumentException(
                "DonorIdentity phone format is invalid: {$phone}"
            );
        }
        if ($pan !== null && ! preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan)) {
            throw new InvalidArgumentException(
                "DonorIdentity PAN format is invalid: {$pan}"
            );
        }
        if ($address !== null) {
            foreach ($address as $key => $value) {
                if (! is_string($key) || ! is_string($value)) {
                    throw new InvalidArgumentException(
                        'DonorIdentity address must be a string=>string map'
                    );
                }
            }
        }
    }

    public static function anonymous(): self
    {
        return new self();
    }

    public static function identified(
        string $name,
        ?string $email = null,
        ?string $phone = null,
        ?string $pan = null,
        ?array $address = null,
    ): self {
        if (trim($name) === '') {
            throw new InvalidArgumentException('Identified donor must have a non-empty name');
        }
        if ($email === null && $phone === null) {
            throw new InvalidArgumentException(
                'Identified donor must have at least one contact channel (email or phone)'
            );
        }

        return new self($name, $email, $phone, $pan, $address);
    }

    public function name(): ?string
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

    public function pan(): ?string
    {
        return $this->pan;
    }

    /**
     * @return array<string, string>|null
     */
    public function address(): ?array
    {
        return $this->address;
    }

    public function isAnonymous(): bool
    {
        return $this->name === null
            && $this->email === null
            && $this->phone === null
            && $this->pan === null
            && $this->address === null;
    }

    public function hasName(): bool
    {
        return $this->name !== null;
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
        return $this->pan !== null;
    }

    public function hasAddress(): bool
    {
        return $this->address !== null && $this->address !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'pan' => $this->pan,
            'address' => $this->address,
            'is_anonymous' => $this->isAnonymous(),
        ];
    }

    /**
     * A donor is considered equal if all PII fields match. Used by tests
     * and idempotency checks.
     */
    public function equals(self $other): bool
    {
        return $this->name === $other->name
            && $this->email === $other->email
            && $this->phone === $other->phone
            && $this->pan === $other->pan
            && $this->address === $other->address;
    }
}