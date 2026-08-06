<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * The typed aggregate carried by `static_pages.about_page_content` (JSONB).
 *
 * V2 grammar (the current kernel supports V2 only). Sections in order
 * of appearance on the Svelte page:
 *
 *   - values     (1) — eyebrow + title + body + image + pillars[3+]
 *   - story      (1) — magazine intro with long body + image
 *   - stats      (3-6) — number/label/description cards
 *   - programs   (3-4) — ongoing programmes with icon
 *   - timeline   (1+) — year-anchored milestones (each may carry an era image)
 *   - trustees   (0+) — leadership cards with photo + bio
 *   - visit      (1) — address, timings, phone, dress code
 *   - donate_cta (1) — bottom donate CTA band copy
 *
 * Validation invariants enforced here (mirrored to Postgres CHECK
 * constraints where applicable):
 *   - non-empty timeline (the page exists to relay chronology)
 *   - 3-6 stats
 *   - 3-4 programs
 *   - every non-null image_file_id (values, story, timeline[*]) resolves
 *     to a known cms_media_assets.id; the existence check is delegated
 *     to the constructor: callers pre-fetch the set of valid media
 *     asset IDs (e.g. via AboutPageContentFactory which uses
 *     PublicMediaQueryContract::findMany) and pass it as
 *     $existingMediaAssetIds. This keeps AboutPageContent free of any
 *     DB reference, so it remains Redis-serializable and side-effect free.
 */
final class AboutPageContent
{
    public const CURRENT_VERSION = 2;

    private const MIN_STATS = 3;
    private const MAX_STATS = 6;
    private const MIN_PROGRAMS = 3;
    private const MAX_PROGRAMS = 4;

    /**
     * @param  list<AboutTimelineEntry>  $timeline
     * @param  list<AboutTrustee>        $trustees
     * @param  list<AboutStat>           $stats
     * @param  list<AboutProgram>        $programs
     * @param  list<string>              $existingMediaAssetIds  Values of cms_media_assets.id
     */
    public function __construct(
        private int $version,
        private AboutValue $values,
        private ?AboutStory $story = null,
        /** @var list<AboutStat> */
        private array $stats = [],
        /** @var list<AboutProgram> */
        private array $programs = [],
        /** @var list<AboutTimelineEntry> */
        private array $timeline,
        /** @var list<AboutTrustee> */
        private array $trustees,
        private ?AboutVisit $visit = null,
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
        $statsCount = count($stats);
        if ($statsCount < self::MIN_STATS || $statsCount > self::MAX_STATS) {
            throw new InvalidArgumentException(
                'AboutPageContent stats must contain between '.self::MIN_STATS
                .' and '.self::MAX_STATS.' entries (got '.$statsCount.')'
            );
        }
        $programsCount = count($programs);
        if ($programsCount < self::MIN_PROGRAMS || $programsCount > self::MAX_PROGRAMS) {
            throw new InvalidArgumentException(
                'AboutPageContent programs must contain between '.self::MIN_PROGRAMS
                .' and '.self::MAX_PROGRAMS.' entries (got '.$programsCount.')'
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
        foreach ($stats as $stat) {
            if (! $stat instanceof AboutStat) {
                throw new InvalidArgumentException(
                    'AboutPageContent stats entries must be AboutStat instances'
                );
            }
        }
        foreach ($programs as $program) {
            if (! $program instanceof AboutProgram) {
                throw new InvalidArgumentException(
                    'AboutPageContent programs entries must be AboutProgram instances'
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

    public function story(): AboutStory
    {
        return $this->story;
    }

    /**
     * @return list<AboutStat>
     */
    public function stats(): array
    {
        return $this->stats;
    }

    /**
     * @return list<AboutProgram>
     */
    public function programs(): array
    {
        return $this->programs;
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

    public function visit(): AboutVisit
    {
        return $this->visit;
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

        $storyId = $this->story->imageFileId();
        if ($storyId !== null) {
            $ids[] = $storyId;
        }

        foreach ($this->timeline as $entry) {
            $entryId = $entry->imageFileId();
            if ($entryId !== null) {
                $ids[] = $entryId;
            }
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
            'story' => $this->story?->toArray(),
            'stats' => array_map(
                static fn (AboutStat $s): array => $s->toArray(),
                $this->stats,
            ),
            'programs' => array_map(
                static fn (AboutProgram $p): array => $p->toArray(),
                $this->programs,
            ),
            'timeline' => array_map(
                static fn (AboutTimelineEntry $e): array => $e->toArray(),
                $this->timeline,
            ),
            'trustees' => array_map(
                static fn (AboutTrustee $t): array => $t->toArray(),
                $this->trustees,
            ),
            'visit' => $this->visit?->toArray(),
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
        $story = AboutStory::fromArray(self::requireArray($row, 'story'));

        $rawStats = $row['stats'] ?? [];
        if (! is_array($rawStats)) {
            throw new InvalidArgumentException('AboutPageContent.stats must be an array');
        }
        $stats = [];
        foreach ($rawStats as $i => $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException(
                    "AboutPageContent.stats entry at index {$i} must be an array"
                );
            }
            $stats[] = AboutStat::fromArray($raw);
        }

        $rawPrograms = $row['programs'] ?? [];
        if (! is_array($rawPrograms)) {
            throw new InvalidArgumentException('AboutPageContent.programs must be an array');
        }
        $programs = [];
        foreach ($rawPrograms as $i => $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException(
                    "AboutPageContent.programs entry at index {$i} must be an array"
                );
            }
            $programs[] = AboutProgram::fromArray($raw);
        }

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

        $visit = AboutVisit::fromArray(self::requireArray($row, 'visit'));
        $donateCta = AboutDonateCta::fromArray(self::requireArray($row, 'donate_cta'));

        return new self(
            version: $version,
            values: $values,
            story: $story,
            stats: $stats,
            programs: $programs,
            timeline: $timeline,
            trustees: $trustees,
            visit: $visit,
            donateCta: $donateCta,
            existingMediaAssetIds: $existingMediaAssetIds,
        );
    }

    /**
     * Inspect a raw payload (pre-construction) and return the list of
     * image_file_id strings referenced in `values`, `story`, each
     * `timeline` entry, and each `trustees` entry. Used by
     * AboutPageContentFactory to pre-fetch the existence set.
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

        $story = $row['story'] ?? null;
        if (is_array($story) && isset($story['image_file_id']) && is_string($story['image_file_id'])
            && $story['image_file_id'] !== '') {
            $ids[] = $story['image_file_id'];
        }

        $timeline = $row['timeline'] ?? [];
        if (is_array($timeline)) {
            foreach ($timeline as $raw) {
                if (is_array($raw)
                    && isset($raw['image_file_id'])
                    && is_string($raw['image_file_id'])
                    && $raw['image_file_id'] !== '') {
                    $ids[] = $raw['image_file_id'];
                }
            }
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