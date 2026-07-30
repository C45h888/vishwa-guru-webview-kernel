<?php

declare(strict_types=1);

namespace App\Cms\Services;

use App\Cms\Domain\ValueObjects\HomepageContent;
use App\Persistence\Contracts\PersistenceAdapterContract;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builds a typed HomepageContent from a raw payload or a stored database
 * value, performing a single batch existence check against
 * cms_media_assets for every referenced image_file_id.
 *
 * The factory is the SOLE place that queries the database for
 * HomepageContent validation. The constructed value object is then
 * pure data — safe to cache, serialize, and pass through the renderer.
 *
 * Caller responsibilities:
 *   - inject HomepageContentFactory via the container
 *   - on every write, call assertStillValid() to revalidate the typed
 *     object against the database between the time it was constructed
 *     and the time it is persisted. This closes the load-to-write
 *     validation interval.
 */
final class HomepageContentFactory
{
    public function __construct(
        private readonly PersistenceAdapterContract $adapter,
    ) {
    }

    /**
     * Build a HomepageContent from a raw array payload. Performs one
     * batch SELECT to validate the image references.
     *
     * @param  array<string, mixed>  $payload
     */
    public function fromArray(array $payload): HomepageContent
    {
        $candidateIds = HomepageContent::referencedImageFileIdsFromArray($payload);
        $existingIds = $this->existingMediaAssetIds($candidateIds);

        return HomepageContent::fromArray($payload, $existingIds);
    }

    /**
     * Build a HomepageContent from a stored database value. Accepts
     * either an already-decoded array or a JSON string. Returns null
     * for null / empty values.
     */
    public function fromDatabaseValue(mixed $value): ?HomepageContent
    {
        if ($value === null || $value === '') {
            return null;
        }

        $decoded = is_array($value)
            ? $value
            : json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new InvalidArgumentException(
                'static_pages.homepage_content must decode to an object'
            );
        }

        return $this->fromArray($decoded);
    }

    /**
     * Re-validate a typed HomepageContent against the current database
     * state. Call this before persisting to close the load-to-write
     * validation interval.
     */
    public function assertStillValid(HomepageContent $content): void
    {
        $payload = $content->toArray();
        $candidateIds = HomepageContent::referencedImageFileIdsFromArray($payload);
        $existingIds = $this->existingMediaAssetIds($candidateIds);

        // Constructing a fresh HomepageContent runs the existence
        // assertion in the value-object constructor.
        HomepageContent::fromArray($payload, $existingIds);
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
                'Unable to validate HomepageContent media references: '
                .($result->error() ?? 'unknown error')
            );
        }

        return array_values(array_map(
            static fn (array $row): string => (string) $row['id'],
            $result->value(),
        ));
    }
}
