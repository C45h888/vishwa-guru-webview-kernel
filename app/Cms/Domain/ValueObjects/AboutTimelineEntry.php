<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A single chronological entry of `static_pages.about_page_content.timeline`.
 *
 * The year is the structural marker — the timeline's order is encoded by
 * the year, not by artificial numbering chips. The Svelte layer renders
 * the year large and serif as the type anchor of each entry.
 */
final readonly class AboutTimelineEntry
{
    public function __construct(
        private int $year,
        private string $title,
        private string $description,
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

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'year' => $this->year,
            'title' => $this->title,
            'description' => $this->description,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            year: (int) ($row['year'] ?? 0),
            title: (string) ($row['title'] ?? ''),
            description: (string) ($row['description'] ?? ''),
        );
    }
}
