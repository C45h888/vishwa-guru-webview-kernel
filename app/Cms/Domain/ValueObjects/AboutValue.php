<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * The single values/info section of `static_pages.about_page_content`.
 *
 * Carries an eyebrow, title, body, optional image reference, and
 * optional alt-text. V2 also carries a `pillars` list — three (or more)
 * short commitment statements rendered as cards under the values
 * block. The pillars are typed as a single string VO (`AboutPillar`)
 * so the Svelte layer has a known shape without having to parse the
 * free-form body.
 *
 * The image_file_id is parsed as an EntityId so downstream consumers
 * receive a typed value; the existence check against cms_media_assets
 * is delegated to AboutPageContent::fromArray().
 *
 * NOTE: $pillars is declared as a regular (non-promoted) typed
 * property and assigned explicitly in the constructor body. This
 * sidesteps a PHP 8.2+ quirk where `readonly` class + promoted
 * property + default value reports "must not be accessed before
 * initialization" when the value is referenced in the constructor
 * body, even though the default would technically be applied.
 */
final class AboutValue
{
    /** @var list<AboutPillar> */
    private mixed $pillars = [];

    public function __construct(
        private string $eyebrow,
        private string $title,
        private string $body,
        private ?EntityId $imageFileId,
        private ?string $altText,
        array $pillars = [],
    ) {
        $this->pillars = $pillars;

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
        foreach ($this->pillars as $pillar) {
            if (! $pillar instanceof AboutPillar) {
                throw new InvalidArgumentException(
                    'AboutValue pillars entries must be AboutPillar instances'
                );
            }
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
     * @return list<AboutPillar>
     */
    public function pillars(): array
    {
        return $this->pillars;
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
            'pillars' => array_map(
                static fn (AboutPillar $p): array => $p->toArray(),
                $this->pillars,
            ),
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

        $rawPillars = $row['pillars'] ?? [];
        $pillars = [];
        if (is_array($rawPillars)) {
            foreach ($rawPillars as $i => $raw) {
                if (! is_array($raw)) {
                    throw new InvalidArgumentException(
                        "AboutValue.pillars entry at index {$i} must be an array"
                    );
                }
                $pillars[] = AboutPillar::fromArray($raw);
            }
        }

        return new self(
            eyebrow: (string) ($row['eyebrow'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            imageFileId: $imageFileId,
            altText: isset($row['alt_text']) ? (string) $row['alt_text'] : null,
            pillars: $pillars,
        );
    }
}