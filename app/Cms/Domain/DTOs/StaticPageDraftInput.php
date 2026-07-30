<?php

declare(strict_types=1);

namespace App\Cms\Domain\DTOs;

use App\Cms\Domain\ValueObjects\HomepageContent;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use InvalidArgumentException;

/**
 * Input DTO for creating a new static page in DRAFT.
 *
 * Constructed by controllers (Phase 4) or admin tooling. Validation
 * happens in the constructor — the service layer trusts that a
 * StaticPageDraftInput is well-formed.
 */
final readonly class StaticPageDraftInput
{
    /**
     * @param  list<array<string, mixed>>  $bodyBlocks  Raw block arrays; validated downstream by PageBody::fromArray
     */
    public function __construct(
        public string $slug,
        public string $title,
        public ?string $metaDescription,
        public array $bodyBlocks,
        public SeoMetadata $seoMetadata,
        public bool $isHomepage = false,
        public int $displayOrder = 0,
        public ?string $createdBy = null,
        public ?HomepageContent $homepageContent = null,
    ) {
        if (trim($title) === '') {
            throw new InvalidArgumentException('StaticPageDraftInput title cannot be empty');
        }
        if (trim($slug) === '') {
            throw new InvalidArgumentException('StaticPageDraftInput slug cannot be empty');
        }
        if ($displayOrder < 0) {
            throw new InvalidArgumentException("displayOrder cannot be negative (got {$displayOrder})");
        }
    }
}
