<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Domain\ValueObjects\AboutPageContent;
use App\Persistence\Contracts\PersistenceAdapterContract;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builds a typed AboutPageContent from a raw payload or a stored database
 * value, performing a single batch existence check against
 * cms_media_assets for every referenced image_file_id.
 *
 * The factory is the SOLE place that queries the database for
 * AboutPageContent validation. The constructed value object is then
 * pure data — safe to cache, serialize, and pass through the renderer.
 *
 * Mirrors HomepageContentFactory. Both factories may be invoked from
 * the same EloquentStaticPageRepository row hydration, but they validate
 * disjoint JSONB columns and are bound separately.
 *
 * Caller responsibilities:
 *   - inject AboutPageContentFactory via the container
 *   - on every write, call assertStillValid() to revalidate the typed
 *     object against the database between the time it was constructed
 *     and the time it is persisted. This closes the load-to-write
 *     validation interval.
 */
final class AboutPageContentFactory
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    /**
     * Build an AboutPageContent from a raw array payload. Performs one
     * batch SELECT to validate the image references.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromArray(array $payload): AboutPageContent
    {
        $candidateIds = AboutPageContent::referencedImageFileIdsFromArray($payload);
        $existingIds = $this->existingMediaAssetIds($candidateIds);

        return AboutPageContent::fromArray($payload, $existingIds);
    }

    /**
     * Build an AboutPageContent from a stored database value. Accepts
     * either an already-decoded array or a JSON string. Returns null
     * for null / empty values.
     */
    public function fromDatabaseValue(mixed $value): ?AboutPageContent
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_array($value)
            ? $value
            : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(
                'static_pages.about_page_content must decode to an object'
            );
        }

        return $this->fromArray($decoded);
    }

    /**
     * Re-validate a typed AboutPageContent against the current database
     * state. Call this before persisting to close the load-to-write
     * validation interval.
     */
    public function assertStillValid(AboutPageContent $content): void
    {
        $payload = $content->toArray();
        $candidateIds = AboutPageContent::referencedImageFileIdsFromArray($payload);
        $existingIds = $this->existingMediaAssetIds($candidateIds);

        // Constructing a fresh AboutPageContent runs the existence
        // assertion in the value-object constructor.
        AboutPageContent::fromArray($payload, $existingIds);
    }

    /**
     * @param  list<string>  $candidateIds
     * @return list<string>
     */
    private function existingMediaAssetIds(array $candidateIds): array
    {
        $candidateIds = array_values(array_unique(array_filter(
            $candidateIds,
            static fn ($id): bool => is_string($id) && $id !== '',
        )));

        if ($candidateIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($candidateIds as $index => $id) {
            $key = 'media_'.$index;
            $params[$key] = $id;
            $placeholders[] = ':'.$key;
        }

        $sql = 'SELECT id FROM cms_media_assets '
            .'WHERE deleted_at IS NULL AND id IN ('.implode(', ', $placeholders).')';

        $result = $this->adapter->query($sql, $params);
        if ($result->isFailure()) {
            throw new RuntimeException(
                'Unable to validate AboutPageContent media references: '
                .($result->error() ?? 'unknown error')
            );
        }

        return array_values(array_map(
            static fn (array $row): string => (string) $row['id'],
            $result->value(),
        ));
    }
}
