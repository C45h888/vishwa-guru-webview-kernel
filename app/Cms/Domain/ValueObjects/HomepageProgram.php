<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Persistence\ValueObjects\EntityId;
use InvalidArgumentException;

/**
 * One of the three program panels in `static_pages.homepage_content`.
 *
 * The key is a closed enum (pooja | annadanam | temple_care). The
 * HomepageContent value object enforces both the count (3) and the
 * canonical order of the program list.
 */
final readonly class HomepageProgram
{
    public const KEY_POOJA = 'pooja';
    public const KEY_ANNADANAM = 'annadanam';
    public const KEY_TEMPLE_CARE = 'temple_care';

    public const ALLOWED_KEYS = [
        self::KEY_POOJA,
        self::KEY_ANNADANAM,
        self::KEY_TEMPLE_CARE,
    ];

    public function __construct(
        private string $key,
        private string $eyebrow,
        private string $title,
        private string $body,
        private ?EntityId $imageFileId,
        private ?string $altText,
    ) {
        if (! in_array($key, self::ALLOWED_KEYS, true)) {
            throw new InvalidArgumentException(
                'HomepageProgram key must be one of '.implode(', ', self::ALLOWED_KEYS)
                .' (got '.$key.')'
            );
        }
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('HomepageProgram eyebrow cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('HomepageProgram title cannot be empty');
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('HomepageProgram body cannot be empty');
        }
        if ($altText !== null && trim($altText) === '') {
            throw new InvalidArgumentException(
                'HomepageProgram altText must be null or non-empty'
            );
        }
    }

    public function key(): string
    {
        return $this->key;
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
            'key' => $this->key,
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
        if (isset($row['image_file_id']) && $row['image_file_id'] !== null) {
            $imageFileId = EntityId::fromString((string) $row['image_file_id']);
        }

        return new self(
            key: (string) ($row['key'] ?? ''),
            eyebrow: (string) ($row['eyebrow'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            body: (string) ($row['body'] ?? ''),
            imageFileId: $imageFileId,
            altText: isset($row['alt_text']) ? (string) $row['alt_text'] : null,
        );
    }
}
