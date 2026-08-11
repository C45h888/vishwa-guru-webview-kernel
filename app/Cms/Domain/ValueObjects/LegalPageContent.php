<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

/**
 * The typed aggregate carried by `static_pages.legal_page_content` (JSONB).
 *
 * V1 grammar (the current kernel supports V1 only). Sections in order
 * of appearance on the Svelte page:
 *
 *   - intro         (1) — eyebrow + title + body
 *   - certificates  (4) — the four regulatory registrations:
 *                        eighty_g (80G), twelve_a (12A),
 *                        poa (Power of Attorney), tan (TAN).
 *
 * Validation invariants enforced here:
 *   - version must equal CURRENT_VERSION (1)
 *   - certificates must contain exactly four entries
 *   - certificate_key set across the array must cover all four
 *     allowed keys exactly once, with no duplicates and no extras
 *   - intro and every certificate must carry non-empty structural
 *     fields (the VO constructors enforce this; this class only
 *     asserts the cross-entry uniqueness)
 *
 * This VO does not validate image references because no
 * certificate carries one today. If a future certificate row needs
 * a cms_media_assets-backed image, follow the AboutPageContent
 * pattern: a list<string> $existingMediaAssetIds parameter and a
 * referencedImageFileIdsFromArray() helper.
 */
final class LegalPageContent
{
    public const CURRENT_VERSION = 1;

    public function __construct(
        private readonly int $version,
        private readonly LegalIntro $intro,
        /** @var list<LegalCertificate> */
        private readonly array $certificates,
    ) {
        if ($version !== self::CURRENT_VERSION) {
            throw new \InvalidArgumentException(
                'LegalPageContent version must be '.self::CURRENT_VERSION
                .' (got '.$version.')'
            );
        }

        $count = count($certificates);
        if ($count !== count(LegalCertificate::ALLOWED_KEYS)) {
            throw new \InvalidArgumentException(
                'LegalPageContent certificates must contain exactly '
                .count(LegalCertificate::ALLOWED_KEYS).' entries (got '.$count.')'
            );
        }

        $seen = [];
        foreach ($certificates as $i => $cert) {
            if (! $cert instanceof LegalCertificate) {
                throw new \InvalidArgumentException(
                    "LegalPageContent certificates[{$i}] must be a LegalCertificate instance"
                );
            }
            $seen[$cert->certificateKey()] = ($seen[$cert->certificateKey()] ?? 0) + 1;
        }

        foreach (LegalCertificate::ALLOWED_KEYS as $requiredKey) {
            if (($seen[$requiredKey] ?? 0) !== 1) {
                throw new \InvalidArgumentException(
                    "LegalPageContent certificates must include certificate_key [{$requiredKey}] exactly once "
                    .'(seen '.($seen[$requiredKey] ?? 0).' times)'
                );
            }
        }
    }

    public function version(): int
    {
        return $this->version;
    }

    public function intro(): LegalIntro
    {
        return $this->intro;
    }

    /**
     * @return list<LegalCertificate>
     */
    public function certificates(): array
    {
        return $this->certificates;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'intro' => $this->intro->toArray(),
            'certificates' => array_map(
                static fn (LegalCertificate $c): array => $c->toArray(),
                $this->certificates,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $version = (int) ($row['version'] ?? 0);

        $intro = LegalIntro::fromArray(self::requireArray($row, 'intro'));

        $raw = $row['certificates'] ?? [];
        if (! is_array($raw)) {
            throw new \InvalidArgumentException('LegalPageContent.certificates must be an array');
        }
        $certs = [];
        foreach ($raw as $i => $entry) {
            if (! is_array($entry)) {
                throw new \InvalidArgumentException(
                    "LegalPageContent.certificates[{$i}] must be an array"
                );
            }
            $certs[] = LegalCertificate::fromArray($entry);
        }

        return new self(
            version: $version,
            intro: $intro,
            certificates: $certs,
        );
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function requireArray(array $row, string $key): array
    {
        $value = $row[$key] ?? null;
        if (! is_array($value)) {
            throw new \InvalidArgumentException("LegalPageContent.{$key} must be an array");
        }

        return $value;
    }
}
