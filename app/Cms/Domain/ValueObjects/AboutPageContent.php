<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * The typed aggregate carried by `static_pages.about_page_content` (JSONB).
 *
 * Versioned; the current kernel supports version 1 only. Enforces:
 *   - non-empty timeline array (allowed by V1 grammar; empty trustees is OK)
 *   - every non-null image_file_id (in values + trustees) resolves to a
 *     known cms_media_assets.id
 *
 * The image-existence check is delegated to the constructor: callers
 * pre-fetch the set of valid media asset IDs (e.g. via
 * AboutPageContentFactory which uses PublicMediaQueryContract::findMany)
 * and pass it as $existingMediaAssetIds. This keeps AboutPageContent
 * free of any DB reference, so it remains Redis-serializable and free
 * of side effects.
 *
 * The timeline is the ONE non-optional list — the About page exists to
 * relay chronology. Trustees may be empty (the section falls back to
 * placeholder cards in the Svelte layer).
 */
final readonly class AboutPageContent
{
    public const CURRENT_VERSION = 1;

    /**
     * @param  list<AboutTimelineEntry>  $timeline
     * @param  list<AboutTrustee>        $trustees
     * @param  list<string>              $existingMediaAssetIds  Values of cms_media_assets.id
     */
    public function __construct(
        private int $version,
        private AboutValue $values,
        private array $timeline,
        private array $trustees,
        private AboutDonateCta $donateCta,
        array $existingMediaAssetIds,
    ) {
        if ($version !== self::CURRENT_VERSION) {
            throw new InvalidArgumentException(
                'AboutPageContent version must be '.self::CURRENT_VERSION
                .' (got '.$version.')'
            );
        }
        if ($timeline === []) {
            throw new InvalidArgumentException(
                'AboutPageContent timeline must contain at least one entry'
            );
        }
        foreach ($timeline as $entry) {
            if (! $entry instanceof AboutTimelineEntry) {
                throw new InvalidArgumentException(
                    'AboutPageContent timeline entries must be AboutTimelineEntry instances'
                );
            }
        }
        foreach ($trustees as $trustee) {
            if (! $trustee instanceof AboutTrustee) {
                throw new InvalidArgumentException(
                    'AboutPageContent trustees entries must be AboutTrustee instances'
                );
            }
        }
        foreach ($existingMediaAssetIds as $id) {
            if (! is_string($id) || $id === '') {
                throw new InvalidArgumentException(
                    'AboutPageContent existingMediaAssetIds must be a list of non-empty strings'
                );
            }
        }

        $known = array_fill_keys($existingMediaAssetIds, true);
        foreach ($this->imageFileIds() as $imageFileId) {
            $id = $imageFileId->value();
            if (! isset($known[$id])) {
                throw new InvalidArgumentException(
                    "AboutPageContent image_file_id [{$id}] does not exist in cms_media_assets"
                );
            }
        }
    }

    public function version(): int
    {
        return $this->version;
    }

    public function values(): AboutValue
    {
        return $this->values;
    }

    /**
     * @return list<AboutTimelineEntry>
     */
    public function timeline(): array
    {
        return $this->timeline;
    }

    /**
     * @return list<AboutTrustee>
     */
    public function trustees(): array
    {
        return $this->trustees;
    }

    public function donateCta(): AboutDonateCta
    {
        return $this->donateCta;
    }

    /**
     * Walk every image reference in the aggregate. Used by the
     * constructor's existence check and by the factory for pre-fetch.
     *
     * @return list<EntityId>
     */
    public function imageFileIds(): array
    {
        $ids = [];
        $valuesId = $this->values->imageFileId();
        if ($valuesId !== null) {
            $ids[] = $valuesId;
        }
        foreach ($this->trustees as $trustee) {
            $photoId = $trustee->photoFileId();
            if ($photoId !== null) {
                $ids[] = $photoId;
            }
        }

        return $ids;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'values' => $this->values->toArray(),
            'timeline' => array_map(
                static fn (AboutTimelineEntry $e): array => $e->toArray(),
                $this->timeline,
            ),
            'trustees' => array_map(
                static fn (AboutTrustee $t): array => $t->toArray(),
                $this->trustees,
            ),
            'donate_cta' => $this->donateCta->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>           $existingMediaAssetIds
     */
    public static function fromArray(array $row, array $existingMediaAssetIds): self
    {
        $version = (int) ($row['version'] ?? 0);
        $values = AboutValue::fromArray(self::requireArray($row, 'values'));

        $rawTimeline = $row['timeline'] ?? [];
        if (! is_array($rawTimeline)) {
            throw new InvalidArgumentException('AboutPageContent.timeline must be an array');
        }
        $timeline = [];
        foreach ($rawTimeline as $i => $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException(
                    "AboutPageContent.timeline entry at index {$i} must be an array"
                );
            }
            $timeline[] = AboutTimelineEntry::fromArray($raw);
        }

        $rawTrustees = $row['trustees'] ?? [];
        if (! is_array($rawTrustees)) {
            throw new InvalidArgumentException('AboutPageContent.trustees must be an array');
        }
        $trustees = [];
        foreach ($rawTrustees as $i => $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException(
                    "AboutPageContent.trustees entry at index {$i} must be an array"
                );
            }
            $trustees[] = AboutTrustee::fromArray($raw);
        }

        $donateCta = AboutDonateCta::fromArray(self::requireArray($row, 'donate_cta'));

        return new self(
            version: $version,
            values: $values,
            timeline: $timeline,
            trustees: $trustees,
            donateCta: $donateCta,
            existingMediaAssetIds: $existingMediaAssetIds,
        );
    }

    /**
     * Inspect a raw payload (pre-construction) and return the list of
     * image_file_id strings referenced in `values` and each `trustees` entry.
     * Used by AboutPageContentFactory to pre-fetch the existence set.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function referencedImageFileIdsFromArray(array $row): array
    {
        $ids = [];

        $values = $row['values'] ?? null;
        if (is_array($values) && isset($values['image_file_id']) && is_string($values['image_file_id'])
            && $values['image_file_id'] !== '') {
            $ids[] = $values['image_file_id'];
        }

        $trustees = $row['trustees'] ?? [];
        if (is_array($trustees)) {
            foreach ($trustees as $raw) {
                if (is_array($raw)
                    && isset($raw['photo_file_id'])
                    && is_string($raw['photo_file_id'])
                    && $raw['photo_file_id'] !== '') {
                    $ids[] = $raw['photo_file_id'];
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function requireArray(array $row, string $key): array
    {
        $value = $row[$key] ?? null;
        if (! is_array($value)) {
            throw new InvalidArgumentException("AboutPageContent.{$key} must be an array");
        }

        return $value;
    }
}
