<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * A single chronological entry of `static_pages.about_page_content.timeline`.
 *
 * The year is the structural marker — the timeline's order is encoded by
 * the year, not by artificial numbering chips. The Svelte layer renders
 * the year large and serif as the type anchor of each entry.
 *
 * V2 adds optional image support: each era may carry a single
 * image_file_id (validated against cms_media_assets by
 * AboutPageContent::fromArray()) plus an alt_text string for the era
 * photo.
 */
final readonly class AboutTimelineEntry
{
    public function __construct(
        private int $year,
        private string $title,
        private string $description,
        private ?EntityId $imageFileId = null,
        private ?string $imageAltText = null,
    ) {
        if ($year < 1900 || $year > 2100) {
            throw new InvalidArgumentException(
                "AboutTimelineEntry year must be between 1900 and 2100 (got {$year})"
            );
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('AboutTimelineEntry title cannot be empty');
        }
        if (trim($description) === '') {
            throw new InvalidArgumentException('AboutTimelineEntry description cannot be empty');
        }
        if ($imageAltText !== null && trim($imageAltText) === '') {
            throw new InvalidArgumentException(
                'AboutTimelineEntry image_alt_text must be null or non-empty'
            );
        }
    }

    public function year(): int
    {
        return $this->year;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function imageFileId(): ?EntityId
    {
        return $this->imageFileId;
    }

    public function imageAltText(): ?string
    {
        return $this->imageAltText;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'title' => $this->title,
            'description' => $this->description,
            'image_file_id' => $this->imageFileId?->value(),
            'image_alt_text' => $this->imageAltText,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $imageFileId = null;
        if (isset($row['image_file_id']) && $row['image_file_id'] !== null && $row['image_file_id'] !== '') {
            $imageFileId = EntityId::fromString((string) $row['image_file_id']);
        }

        $imageAltText = isset($row['image_alt_text']) && $row['image_alt_text'] !== ''
            ? (string) $row['image_alt_text']
            : null;

        return new self(
            year: (int) ($row['year'] ?? 0),
            title: (string) ($row['title'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            imageFileId: $imageFileId,
            imageAltText: $imageAltText,
        );
    }
}