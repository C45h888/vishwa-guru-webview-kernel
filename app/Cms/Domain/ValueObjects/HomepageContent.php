<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * The typed aggregate carried by `static_pages.homepage_content` (JSONB).
 *
 * Versioned; the current kernel supports version 1 only. Enforces:
 *   - exactly 3 programs in canonical order (pooja, annadanam, temple_care)
 *   - every non-null image_file_id resolves to a known cms_media_assets.id
 *
 * The image-existence check is delegated to the constructor: callers
 * pre-fetch the set of valid media asset IDs (e.g. via HomepageContentFactory
 * which uses PublicMediaQueryContract::findMany) and pass it as
 * $existingMediaAssetIds. This keeps HomepageContent free of any DB
 * reference, so it remains Redis-serializable and free of side effects.
 */
final readonly class HomepageContent
{
    public const CURRENT_VERSION = 1;

    /**
     * @param  list<HomepageProgram>  $programs
     * @param  list<string>           $existingMediaAssetIds  Values of cms_media_assets.id
     */
    public function __construct(
        private int $version,
        private HomepageStory $story,
        private HomepageMissionQuote $missionQuote,
        private array $programs,
        private HomepageTrustPanel $trustPanel,
        private HomepageDonateCta $donateCta,
        array $existingMediaAssetIds,
    ) {
        if ($version !== self::CURRENT_VERSION) {
            throw new InvalidArgumentException(
                'HomepageContent version must be '.self::CURRENT_VERSION
                .' (got '.$version.')'
            );
        }
        if (count($programs) !== 3) {
            throw new InvalidArgumentException(
                'HomepageContent must contain exactly three programs (got '.count($programs).')'
            );
        }
        $keys = array_map(static fn (HomepageProgram $p): string => $p->key(), $programs);
        if ($keys !== HomepageProgram::ALLOWED_KEYS) {
            throw new InvalidArgumentException(
                'HomepageContent programs must be ordered pooja, annadanam, temple_care '
                .'(got '.implode(', ', $keys).')'
            );
        }
        foreach ($existingMediaAssetIds as $id) {
            if (! is_string($id) || $id === '') {
                throw new InvalidArgumentException(
                    'HomepageContent existingMediaAssetIds must be a list of non-empty strings'
                );
            }
        }

        $known = array_fill_keys($existingMediaAssetIds, true);
        foreach ($this->imageFileIds() as $imageFileId) {
            $id = $imageFileId->value();
            if (! isset($known[$id])) {
                throw new InvalidArgumentException(
                    "HomepageContent image_file_id [{$id}] does not exist in cms_media_assets"
                );
            }
        }
    }

    /**
     * @return list<EntityId>
     */
    public function imageFileIds(): array
    {
        $ids = [];
        $story = $this->story->imageFileId();
        if ($story !== null) {
            $ids[] = $story;
        }
        foreach ($this->programs as $program) {
            $programId = $program->imageFileId();
            if ($programId !== null) {
                $ids[] = $programId;
            }
        }

        return $ids;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function story(): HomepageStory
    {
        return $this->story;
    }

    public function missionQuote(): HomepageMissionQuote
    {
        return $this->missionQuote;
    }

    /**
     * @return list<HomepageProgram>
     */
    public function programs(): array
    {
        return $this->programs;
    }

    public function trustPanel(): HomepageTrustPanel
    {
        return $this->trustPanel;
    }

    public function donateCta(): HomepageDonateCta
    {
        return $this->donateCta;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'story' => $this->story->toArray(),
            'mission_quote' => $this->missionQuote->toArray(),
            'programs' => array_map(
                static fn (HomepageProgram $p): array => $p->toArray(),
                $this->programs,
            ),
            'trust_panel' => $this->trustPanel->toArray(),
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
        $story = HomepageStory::fromArray(self::requireArray($row, 'story'));
        $missionQuote = HomepageMissionQuote::fromArray(self::requireArray($row, 'mission_quote'));

        $rawPrograms = $row['programs'] ?? [];
        if (! is_array($rawPrograms)) {
            throw new InvalidArgumentException('HomepageContent.programs must be an array');
        }
        $programs = [];
        foreach ($rawPrograms as $i => $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException(
                    "HomepageContent.programs entry at index {$i} must be an array"
                );
            }
            $programs[] = HomepageProgram::fromArray($raw);
        }

        $trustPanel = HomepageTrustPanel::fromArray(self::requireArray($row, 'trust_panel'));
        $donateCta = HomepageDonateCta::fromArray(self::requireArray($row, 'donate_cta'));

        return new self(
            version: $version,
            story: $story,
            missionQuote: $missionQuote,
            programs: $programs,
            trustPanel: $trustPanel,
            donateCta: $donateCta,
            existingMediaAssetIds: $existingMediaAssetIds,
        );
    }

    /**
     * Inspect a raw payload (pre-construction) and return the list of
     * image_file_id strings referenced in the story and each program.
     * Used by HomepageContentFactory to pre-fetch the existence set.
     *
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    public static function referencedImageFileIdsFromArray(array $row): array
    {
        $ids = [];

        $story = $row['story'] ?? null;
        if (is_array($story) && isset($story['image_file_id']) && is_string($story['image_file_id'])
            && $story['image_file_id'] !== '') {
            $ids[] = $story['image_file_id'];
        }

        $programs = $row['programs'] ?? [];
        if (is_array($programs)) {
            foreach ($programs as $raw) {
                if (is_array($raw)
                    && isset($raw['image_file_id'])
                    && is_string($raw['image_file_id'])
                    && $raw['image_file_id'] !== '') {
                    $ids[] = $raw['image_file_id'];
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
            throw new InvalidArgumentException("HomepageContent.{$key} must be an array");
        }

        return $value;
    }
}
