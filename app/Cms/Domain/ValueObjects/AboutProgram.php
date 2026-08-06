<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A single ongoing programme on the About page (V2+).
 *
 * Programmes are the "what we do every day" surface — daily pooja,
 * annadanam, priest training, structure restoration, and so on. Each
 * carries an eyebrow (a short category line), a serif title, a body
 * paragraph, and an icon_key the Svelte layer maps to a lucide icon.
 *
 * icon_key vocabulary (V2 grammar): pooja | annadanam | training | care
 * | scripture | community. Unknown keys fall back to a generic dot
 * icon in the Svelte layer so the page never breaks on a new key.
 */
final readonly class AboutProgram
{
    public function __construct(
        private string $eyebrow,
        private string $title,
        private string $body,
        private string $iconKey,
    ) {
        if (trim($eyebrow) === '') {
            throw new InvalidArgumentException('AboutProgram eyebrow cannot be empty');
        }
        if (trim($title) === '') {
            throw new InvalidArgumentException('AboutProgram title cannot be empty');
        }
        if (trim($body) === '') {
            throw new InvalidArgumentException('AboutProgram body cannot be empty');
        }
        if (trim($iconKey) === '') {
            throw new InvalidArgumentException('AboutProgram icon_key cannot be empty');
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

    public function iconKey(): string
    {
        return $this->iconKey;
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
            'icon_key' => $this->iconKey,
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
            iconKey: (string) ($row['icon_key'] ?? ''),
        );
    }
}