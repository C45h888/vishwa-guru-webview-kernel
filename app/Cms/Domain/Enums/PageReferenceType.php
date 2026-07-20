<?php

declare(strict_types=1);

namespace App\Cms\Domain\Enums;

/**
 * The category of entity a Static Page references via
 * `static_page_references.reference_type`.
 *
 * String values match the PostgreSQL `page_reference_type` enum defined
 * in `database/schema-neon/V1-schema.sql` line 146-150 exactly.
 *
 * V1 only resolves CAMPAIGN (the Payments kernel exposes the read-side
 * contract via Shape A bridge). GALLERY_IMAGE and EVENT are reserved
 * for future kernels; the resolver returns `status = Unresolved` for
 * them until those kernels ship.
 */
enum PageReferenceType: string
{
    case CAMPAIGN = 'campaign';
    case GALLERY_IMAGE = 'gallery_image';
    case EVENT = 'event';

    /**
     * Whether this reference type can be resolved by V1's kernel bridge.
     * True only for CAMPAIGN; Gallery and Events kernels are deferred.
     */
    public function isResolvableInV1(): bool
    {
        return $this === self::CAMPAIGN;
    }

    /**
     * Display label for admin UI surfaces.
     */
    public function label(): string
    {
        return match ($this) {
            self::CAMPAIGN => 'Campaign',
            self::GALLERY_IMAGE => 'Gallery Image',
            self::EVENT => 'Event',
        };
    }
}