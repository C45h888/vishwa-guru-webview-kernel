<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Cms\Domain\Enums\PublicMediaState;
use App\Cms\Domain\Enums\PublicMediaType;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Immutable value object mirroring the cms_media_assets table row.
 *
 * cms_media_assets is a typed-overlay leaf entity — it has no state
 * machine, no EntityId wrapper, and no EntityContract. The schema
 * stores raw 26-char ULIDs as TEXT without an entity-type prefix,
 * consistent with file_assets.id.
 *
 * @property string                         $id
 * @property string                         $fileAssetId       FK -> file_assets.id (UNIQUE)
 * @property PublicMediaType                $mediaType         10-value enum
 * @property PublicMediaState              $state             4-value enum
 * @property string|null                   $altText
 * @property string|null                   $caption
 * @property string|null                   $credit
 * @property int|null                      $width
 * @property int|null                      $height
 * @property float|null                    $focalX
 * @property float|null                    $focalY
 * @property string|null                   $variantGroupId
 * @property DateTimeImmutable|null        $publishedAt
 * @property DateTimeImmutable|null        $archivedAt
 * @property DateTimeImmutable             $createdAt
 * @property DateTimeImmutable             $updatedAt
 * @property DateTimeImmutable|null        $deletedAt
 * @property string|null                   $createdBy
 * @property string|null                   $updatedBy
 */
final readonly class CmsMediaAssetRecord
{
    public function __construct(
        private string $id,
        private string $fileAssetId,
        private PublicMediaType $mediaType,
        private PublicMediaState $state,
        private ?string $altText = null,
        private ?string $caption = null,
        private ?string $credit = null,
        private ?int $width = null,
        private ?int $height = null,
        private ?float $focalX = null,
        private ?float $focalY = null,
        private ?string $variantGroupId = null,
        private ?DateTimeImmutable $publishedAt = null,
        private ?DateTimeImmutable $archivedAt = null,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt = null,
        private ?string $createdBy = null,
        private ?string $updatedBy = null,
    ) {}

    /**
     * Factory from constructor arguments.
     *
     * @param  array<string, mixed>|null  $metadata  (unused, present for compatibility with file_assets shape)
     */
    public static function create(
        string $id,
        string $fileAssetId,
        PublicMediaType $mediaType,
        PublicMediaState $state,
        ?string $altText = null,
        ?string $caption = null,
        ?string $credit = null,
        ?int $width = null,
        ?int $height = null,
        ?float $focalX = null,
        ?float $focalY = null,
        ?string $variantGroupId = null,
        ?DateTimeImmutable $publishedAt = null,
        ?DateTimeImmutable $archivedAt = null,
        ?string $createdBy = null,
        ?string $updatedBy = null,
    ): self {
        self::validate(
            id: $id,
            fileAssetId: $fileAssetId,
            state: $state,
            altText: $altText,
            publishedAt: $publishedAt,
            archivedAt: $archivedAt,
            width: $width,
            height: $height,
        );

        $now = new DateTimeImmutable();

        return new self(
            id: $id,
            fileAssetId: $fileAssetId,
            mediaType: $mediaType,
            state: $state,
            altText: $altText,
            caption: $caption,
            credit: $credit,
            width: $width,
            height: $height,
            focalX: $focalX,
            focalY: $focalY,
            variantGroupId: $variantGroupId,
            publishedAt: $publishedAt,
            archivedAt: $archivedAt,
            createdAt: $now,
            updatedAt: $now,
            deletedAt: null,
            createdBy: $createdBy ?? 'system',
            updatedBy: $updatedBy ?? 'system',
        );
    }

    /**
     * Rehydrate from a database row.
     *
     * @param  array<string, mixed>  $row
     */
    public static function fromRow(array $row): self
    {
        $required = [
            'id', 'file_asset_id', 'media_type', 'state', 'created_at', 'updated_at',
        ];

        foreach ($required as $key) {
            if (! array_key_exists($key, $row)) {
                throw new InvalidArgumentException("CmsMediaAssetRecord row missing required key: {$key}");
            }
        }

        return new self(
            id: (string) $row['id'],
            fileAssetId: (string) $row['file_asset_id'],
            mediaType: PublicMediaType::from((string) $row['media_type']),
            state: PublicMediaState::from((string) $row['state']),
            altText: isset($row['alt_text']) && $row['alt_text'] !== '' ? (string) $row['alt_text'] : null,
            caption: isset($row['caption']) && $row['caption'] !== '' ? (string) $row['caption'] : null,
            credit: isset($row['credit']) && $row['credit'] !== '' ? (string) $row['credit'] : null,
            width: isset($row['width']) && $row['width'] !== null ? (int) $row['width'] : null,
            height: isset($row['height']) && $row['height'] !== null ? (int) $row['height'] : null,
            focalX: isset($row['focal_x']) && $row['focal_x'] !== null ? (float) $row['focal_x'] : null,
            focalY: isset($row['focal_y']) && $row['focal_y'] !== null ? (float) $row['focal_y'] : null,
            variantGroupId: isset($row['variant_group_id']) && $row['variant_group_id'] !== '' ? (string) $row['variant_group_id'] : null,
            publishedAt: self::parseDate($row['published_at'] ?? null),
            archivedAt: self::parseDate($row['archived_at'] ?? null),
            createdAt: self::parseDate($row['created_at']) ?? new DateTimeImmutable(),
            updatedAt: self::parseDate($row['updated_at']) ?? new DateTimeImmutable(),
            deletedAt: self::parseDate($row['deleted_at'] ?? null),
            createdBy: isset($row['created_by']) && $row['created_by'] !== '' ? (string) $row['created_by'] : null,
            updatedBy: isset($row['updated_by']) && $row['updated_by'] !== '' ? (string) $row['updated_by'] : null,
        );
    }

    private static function validate(
        string $id,
        string $fileAssetId,
        PublicMediaState $state,
        ?string $altText,
        ?DateTimeImmutable $publishedAt,
        ?DateTimeImmutable $archivedAt,
        ?int $width,
        ?int $height,
    ): void {
        if ($id === '') {
            throw new InvalidArgumentException('CmsMediaAssetRecord id cannot be empty');
        }
        if ($fileAssetId === '') {
            throw new InvalidArgumentException('CmsMediaAssetRecord fileAssetId cannot be empty');
        }
        if ($width !== null && $width <= 0) {
            throw new InvalidArgumentException(
                "CmsMediaAssetRecord width must be null or > 0 (got {$width})",
            );
        }
        if ($height !== null && $height <= 0) {
            throw new InvalidArgumentException(
                "CmsMediaAssetRecord height must be null or > 0 (got {$height})",
            );
        }
        // cms_media_publish_metadata CHECK — mirrors Postgres constraint
        if ($state === PublicMediaState::PUBLISHED) {
            if ($altText === null || $altText === '') {
                throw new InvalidArgumentException(
                    'CmsMediaAssetRecord: published assets must have altText',
                );
            }
            if ($publishedAt === null) {
                throw new InvalidArgumentException(
                    'CmsMediaAssetRecord: published assets must have publishedAt',
                );
            }
        }
        // cms_media_archive_timestamp CHECK — mirrors Postgres constraint
        if ($state === PublicMediaState::ARCHIVED) {
            if ($archivedAt === null) {
                throw new InvalidArgumentException(
                    'CmsMediaAssetRecord: archived assets must have archivedAt',
                );
            }
        }
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        return new DateTimeImmutable((string) $value);
    }

    // ─── Getters ────────────────────────────────────────────────────────

    public function id(): string { return $this->id; }
    public function fileAssetId(): string { return $this->fileAssetId; }
    public function mediaType(): PublicMediaType { return $this->mediaType; }
    public function state(): PublicMediaState { return $this->state; }
    public function altText(): ?string { return $this->altText; }
    public function caption(): ?string { return $this->caption; }
    public function credit(): ?string { return $this->credit; }
    public function width(): ?int { return $this->width; }
    public function height(): ?int { return $this->height; }
    public function focalX(): ?float { return $this->focalX; }
    public function focalY(): ?float { return $this->focalY; }
    public function variantGroupId(): ?string { return $this->variantGroupId; }
    public function publishedAt(): ?DateTimeImmutable { return $this->publishedAt; }
    public function archivedAt(): ?DateTimeImmutable { return $this->archivedAt; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function deletedAt(): ?DateTimeImmutable { return $this->deletedAt; }
    public function createdBy(): ?string { return $this->createdBy; }
    public function updatedBy(): ?string { return $this->updatedBy; }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'file_asset_id' => $this->fileAssetId,
            'media_type' => $this->mediaType->value,
            'state' => $this->state->value,
            'alt_text' => $this->altText,
            'caption' => $this->caption,
            'credit' => $this->credit,
            'width' => $this->width,
            'height' => $this->height,
            'focal_x' => $this->focalX,
            'focal_y' => $this->focalY,
            'variant_group_id' => $this->variantGroupId,
            'published_at' => $this->publishedAt?->format(DATE_ATOM),
            'archived_at' => $this->archivedAt?->format(DATE_ATOM),
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
            'deleted_at' => $this->deletedAt?->format(DATE_ATOM),
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }
}
