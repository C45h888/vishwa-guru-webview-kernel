<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * The trust-story prose block of `static_pages.homepage_content`.
 *
 * Carries the eyebrow, title, body, optional CTA, and optional image
 * reference. The image_file_id is parsed as an EntityId so downstream
 * consumers receive a typed value; the existence check against
 * cms_media_assets is delegated to HomepageContent::fromArray().
 */
final readonly class HomepageStory
{
    public function __construct(
        private string $eyebrow,
        private string $title,
        private string $body,
        private ?string $ctaLabel,
        private ?string $ctaUrl,
        private ?EntityId $imageFileId,
        private ?string $altText,
    ) {
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('HomepageStory eyebrow cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('HomepageStory title cannot be empty');
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('HomepageStory body cannot be empty');
        }

        $hasLabel = $ctaLabel !== null && trim($ctaLabel) !== '';
        $hasUrl = $ctaUrl !== null && trim($ctaUrl) !== '';
        if ($hasLabel xor $hasUrl) {
            throw new InvalidArgumentException(
                'HomepageStory cta_label and cta_url must be set together (both null or both non-empty)'
            );
        }
        if ($hasUrl && ! str_starts_with($ctaUrl, '/')) {
            throw new InvalidArgumentException(
                'HomepageStory cta_url must be a site-relative path beginning with /'
            );
        }
        if ($altText !== null && trim($altText) === '') {
            throw new InvalidArgumentException('HomepageStory altText must be null or non-empty');
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

    public function ctaLabel(): ?string
    {
        return $this->ctaLabel;
    }

    public function ctaUrl(): ?string
    {
        return $this->ctaUrl;
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
            'cta_label' => $this->ctaLabel,
            'cta_url' => $this->ctaUrl,
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
        if (isset($row['image_file_id']) && $row['image_file_id'] !== null) {
            $imageFileId = EntityId::fromString((string) $row['image_file_id']);
        }

        return new self(
            eyebrow: (string) ($row['eyebrow'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            ctaLabel: isset($row['cta_label']) ? (string) $row['cta_label'] : null,
            ctaUrl: isset($row['cta_url']) ? (string) $row['cta_url'] : null,
            imageFileId: $imageFileId,
            altText: isset($row['alt_text']) ? (string) $row['alt_text'] : null,
        );
    }
}
