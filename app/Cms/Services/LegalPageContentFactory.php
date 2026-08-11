<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Domain\ValueObjects\LegalPageContent;
use InvalidArgumentException;

/**
 * Builds a typed LegalPageContent from a raw payload or a stored database
 * value.
 *
 * The factory is the SOLE place that decodes the
 * `static_pages.legal_page_content` JSONB column. The constructed value
 * object is then pure data — safe to cache, serialize, and pass through
 * the renderer.
 *
 * Mirrors AboutPageContentFactory and HomepageContentFactory.
 *
 * Caller responsibilities:
 *   - inject LegalPageContentFactory via the container
 *   - on every write, call assertStillValid() to revalidate the typed
 *     object between the time it was constructed and the time it is
 *     persisted. This closes the load-to-write validation interval.
 */
final class LegalPageContentFactory
{
    /**
     * Build a LegalPageContent from a raw array payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromArray(array $payload): LegalPageContent
    {
        return LegalPageContent::fromArray($payload);
    }

    /**
     * Build a LegalPageContent from a stored database value. Accepts
     * either an already-decoded array or a JSON string. Returns null
     * for null / empty values.
     */
    public function fromDatabaseValue(mixed $value): ?LegalPageContent
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_array($value)
            ? $value
            : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(
                'static_pages.legal_page_content must decode to an object'
            );
        }

        return $this->fromArray($decoded);
    }

    /**
     * Re-validate a typed LegalPageContent against the current payload.
     * Call this before persisting to close the load-to-write validation
     * interval. LegalPageContent has no media references today, so the
     * pre-fetch step is a no-op; the assertStillValid pattern is kept
     * for forward-compatibility (a future certificate image would
     * require a cms_media_assets existence check, mirroring About).
     */
    public function assertStillValid(LegalPageContent $content): void
    {
        LegalPageContent::fromArray($content->toArray());
    }
}
