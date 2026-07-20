<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;

/**
 * SEO metadata carrier for static_pages.seo_metadata (JSONB).
 *
 * Holds the SEO-relevant fields separately from the page body so the
 * admin UI can edit them in dedicated form sections. JSON serialization
 * uses snake_case keys to match the schema convention.
 */
final readonly class SeoMetadata
{
    /**
     * @param  list<string>  $keywords
     */
    public function __construct(
        private readonly ?string $metaTitle = null,
        private readonly ?string $metaDescription = null,
        private readonly ?string $canonicalUrl = null,
        private readonly ?EntityId $ogImageFileId = null,
        private readonly array $keywords = [],
    ) {
        foreach ($keywords as $kw) {
            if (! is_string($kw) || trim($kw) === '') {
                throw new \InvalidArgumentException('SEO keywords must be non-empty strings');
            }
        }
    }

    public function metaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function metaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function canonicalUrl(): ?string
    {
        return $this->canonicalUrl;
    }

    public function ogImageFileId(): ?EntityId
    {
        return $this->ogImageFileId;
    }

    /**
     * @return list<string>
     */
    public function keywords(): array
    {
        return $this->keywords;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'meta_title' => $this->metaTitle,
            'meta_description' => $this->metaDescription,
            'canonical_url' => $this->canonicalUrl,
            'og_image_file_id' => $this->ogImageFileId?->value(),
            'keywords' => $this->keywords,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $ogImageFileId = null;
        if (isset($row['og_image_file_id']) && $row['og_image_file_id'] !== null) {
            $ogImageFileId = EntityId::fromString((string) $row['og_image_file_id']);
        }

        $keywords = [];
        if (isset($row['keywords']) && is_array($row['keywords'])) {
            $keywords = array_values(array_filter(
                array_map(static fn ($k) => is_string($k) ? trim($k) : '', $row['keywords']),
                static fn ($k) => $k !== '',
            ));
        }

        return new self(
            metaTitle: isset($row['meta_title']) ? (string) $row['meta_title'] : null,
            metaDescription: isset($row['meta_description']) ? (string) $row['meta_description'] : null,
            canonicalUrl: isset($row['canonical_url']) ? (string) $row['canonical_url'] : null,
            ogImageFileId: $ogImageFileId,
            keywords: $keywords,
        );
    }
}