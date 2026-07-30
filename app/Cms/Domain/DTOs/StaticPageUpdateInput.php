<?php

declare(strict_types=1);

namespace App\Cms\Domain\DTOs;

use App\Cms\Domain\ValueObjects\HomepageContent;
use App\Cms\Domain\ValueObjects\SeoMetadata;
use InvalidArgumentException;

/**
 * Input DTO for updating an existing static page's content.
 *
 * All fields nullable. Service merges non-null fields onto the entity
 * via withChanges(). Setting a field to null is "do not change"; pass
 * empty string or empty array to explicitly clear an optional field.
 *
 * homepageContent is treated specially: callers pass a non-null value
 * to replace the existing aggregate. To explicitly clear the
 * homepage prose, set $clearHomepageContent = true and leave
 * $homepageContent null. Setting both is a programming error.
 */
final class StaticPageUpdateInput
{
    /**
     * @param  list<array<string, mixed>>|null  $bodyBlocks
     */
    public function __construct(
        public ?string $title = null,
        public ?string $metaDescription = null,
        public ?array $bodyBlocks = null,
        public ?SeoMetadata $seoMetadata = null,
        public ?int $displayOrder = null,
        public ?string $updatedBy = null,
        public ?HomepageContent $homepageContent = null,
        public bool $clearHomepageContent = false,
    ) {
        if ($title !== null && trim($title) === '') {
            throw new InvalidArgumentException('StaticPageUpdateInput title cannot be empty string');
        }
        if ($displayOrder !== null && $displayOrder < 0) {
            throw new InvalidArgumentException("displayOrder cannot be negative (got {$displayOrder})");
        }
        if ($homepageContent !== null && $clearHomepageContent) {
            throw new InvalidArgumentException(
                'StaticPageUpdateInput cannot simultaneously set homepageContent and clearHomepageContent'
            );
        }
    }
}
