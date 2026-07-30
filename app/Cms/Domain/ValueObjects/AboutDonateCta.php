<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * The donate-CTA band copy for `static_pages.about_page_content`.
 *
 * Shape is identical to HomepageDonateCta — separate VO so the two
 * aggregates remain independently evolvable across grammar versions.
 */
final readonly class AboutDonateCta
{
    public function __construct(
        private string $eyebrow,
        private string $title,
        private string $body,
        private string $ctaLabel,
        private string $ctaUrl,
    ) {
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('AboutDonateCta eyebrow cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('AboutDonateCta title cannot be empty');
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('AboutDonateCta body cannot be empty');
        }
        if (trim($ctaLabel) === '') {
            throw new InvalidArgumentException('AboutDonateCta cta_label cannot be empty');
        }
        if (trim($ctaUrl) === '' || ! str_starts_with($ctaUrl, '/')) {
            throw new InvalidArgumentException(
                'AboutDonateCta cta_url must be a site-relative path beginning with /'
            );
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

    public function ctaLabel(): string
    {
        return $this->ctaLabel;
    }

    public function ctaUrl(): string
    {
        return $this->ctaUrl;
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
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            eyebrow: (string) ($row['eyebrow'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            ctaLabel: (string) ($row['cta_label'] ?? ''),
            ctaUrl: (string) ($row['cta_url'] ?? ''),
        );
    }
}
