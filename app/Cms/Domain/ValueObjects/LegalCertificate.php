<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

/**
 * A single regulatory certificate row on the Legal page.
 *
 * Holds the public-facing copy that appears on one of the four
 * certificate cards (80G, 12A, Power of Attorney, TAN). The
 * `certificate_key` is a stable internal identifier that the Svelte
 * layer uses to pick an icon and to anchor scroll behavior; the
 * `icon_key` is the lucide-svelte icon key the front-end resolves.
 *
 * `reference_number` is nullable because the trust office may not yet
 * have confirmed the official reference number — the page renders a
 * styled placeholder until the value lands. Validation here is purely
 * structural; the public-facing copy on `description` carries the
 * compliance language and must not be invented by non-trust staff.
 *
 * The remaining public-facing fields are also nullable so the VO can
 * represent an in-progress certificate (admin editor with placeholder
 * content) as well as a fully-populated one (data scraped from the
 * issued PDF). The Legal page renders placeholder copy only when the
 * underlying JSONB row is missing entirely; partial data shows what
 * is known and renders a placeholder for what is not.
 */
final class LegalCertificate
{
    public const ALLOWED_KEYS = ['eighty_g', 'twelve_a', 'poa', 'tan'];

    public function __construct(
        private readonly string $certificateKey,
        private readonly string $title,
        private readonly ?string $referenceNumber,
        private readonly string $description,
        private readonly string $iconKey,
        private readonly ?string $validityPeriod = null,
        private readonly ?string $issuedOn = null,
        private readonly ?string $issuingAuthority = null,
    ) {
        if (! in_array($this->certificateKey, self::ALLOWED_KEYS, true)) {
            throw new \InvalidArgumentException(
                "LegalCertificate.certificate_key [{$this->certificateKey}] is not in the allowed set: "
                .implode(', ', self::ALLOWED_KEYS)
            );
        }
        if (trim($this->title) === '') {
            throw new \InvalidArgumentException('LegalCertificate title cannot be empty');
        }
        if (trim($this->description) === '') {
            throw new \InvalidArgumentException('LegalCertificate description cannot be empty');
        }
        if (trim($this->iconKey) === '') {
            throw new \InvalidArgumentException('LegalCertificate icon_key cannot be empty');
        }
        if ($this->referenceNumber !== null && trim($this->referenceNumber) === '') {
            throw new \InvalidArgumentException(
                'LegalCertificate reference_number cannot be an empty string; pass null for "not yet confirmed"'
            );
        }
        foreach (['validityPeriod', 'issuedOn', 'issuingAuthority'] as $field) {
            $val = $this->$field;
            if ($val !== null && trim($val) === '') {
                throw new \InvalidArgumentException(
                    "LegalCertificate.{$field} cannot be an empty string; pass null if unknown"
                );
            }
        }
    }

    public function certificateKey(): string
    {
        return $this->certificateKey;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function referenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function iconKey(): string
    {
        return $this->iconKey;
    }

    public function validityPeriod(): ?string
    {
        return $this->validityPeriod;
    }

    public function issuedOn(): ?string
    {
        return $this->issuedOn;
    }

    public function issuingAuthority(): ?string
    {
        return $this->issuingAuthority;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'certificate_key' => $this->certificateKey,
            'title' => $this->title,
            'reference_number' => $this->referenceNumber,
            'description' => $this->description,
            'icon_key' => $this->iconKey,
            'validity_period' => $this->validityPeriod,
            'issued_on' => $this->issuedOn,
            'issuing_authority' => $this->issuingAuthority,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $key = isset($row['certificate_key']) && is_string($row['certificate_key'])
            ? $row['certificate_key']
            : '';
        $title = isset($row['title']) && is_string($row['title'])
            ? $row['title']
            : '';
        $refRaw = $row['reference_number'] ?? null;
        $ref = is_string($refRaw) ? $refRaw : null;
        $description = isset($row['description']) && is_string($row['description'])
            ? $row['description']
            : '';
        $icon = isset($row['icon_key']) && is_string($row['icon_key'])
            ? $row['icon_key']
            : '';
        $vpRaw = $row['validity_period'] ?? null;
        $vp = is_string($vpRaw) ? $vpRaw : null;
        $ioRaw = $row['issued_on'] ?? null;
        $io = is_string($ioRaw) ? $ioRaw : null;
        $iaRaw = $row['issuing_authority'] ?? null;
        $ia = is_string($iaRaw) ? $iaRaw : null;

        return new self(
            certificateKey: $key,
            title: $title,
            referenceNumber: $ref,
            description: $description,
            iconKey: $icon,
            validityPeriod: $vp,
            issuedOn: $io,
            issuingAuthority: $ia,
        );
    }
}
