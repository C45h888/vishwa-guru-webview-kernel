<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * The single values/info section of `static_pages.about_page_content`.
 *
 * Carries an eyebrow, title, body, optional image reference, and optional
 * alt-text. The image_file_id is parsed as an EntityId so downstream
 * consumers receive a typed value; the existence check against
 * cms_media_assets is delegated to AboutPageContent::fromArray().
 *
 * V1 supports a single value section (the user said "generic values and
 * information for now"). A future grammar version bump may replace this
 * with a list-of-N values wrapper.
 */
final readonly class AboutValue
{
    public function __construct(
        private string $eyebrow,
        private string $title,
        private string $body,
        private ?EntityId $imageFileId,
        private ?string $altText,
    ) {
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('AboutValue eyebrow cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('AboutValue title cannot be empty');
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('AboutValue body cannot be empty');
        }
        if ($altText !== null && trim($altText) === '') {
            throw new InvalidArgumentException('AboutValue altText must be null or non-empty');
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
