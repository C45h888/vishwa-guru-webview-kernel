<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * The "Our story" section of `static_pages.about_page_content` (V2+).
 *
 * Magazine-style intro that bridges the hero and the rest of the page.
 * Carries a long prose body, an optional hero image, and standard
 * eyebrow + title chrome. The image_file_id is parsed as an EntityId;
 * the existence check against cms_media_assets is delegated to
 * AboutPageContent::fromArray().
 */
final readonly class AboutStory
{
    public function __construct(
        private string $eyebrow,
        private string $title,
        private string $body,
        private ?EntityId $imageFileId,
        private ?string $altText,
    ) {
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('AboutStory eyebrow cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('AboutStory title cannot be empty');
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('AboutStory body cannot be empty');
        }
        if ($altText !== null && trim($altText) === '') {
            throw new InvalidArgumentException('AboutStory altText must be null or non-empty');
        }
    }

    public function eyebrow(): string
    {
        return $this->eyebrow;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function imageFileId(): ?EntityId
    {
        return $this->imageFileId;
    }

    public function altText(): ?string
    {
        return $this->altText;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'body' => $this->body,
            'image_file_id' => $this->imageFileId?->value(),
            'alt_text' => $this->altText,
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

        return new self(
            eyebrow: (string) ($row['eyebrow'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            imageFileId: $imageFileId,
            altText: isset($row['alt_text']) ? (string) $row['alt_text'] : null,
        );
    }
}