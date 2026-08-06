<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * A single commitment card under the About values section (V2+).
 *
 * Used by AboutValue::pillars to render the trust's operating
 * commitments as a row of cards under the main values prose. Each
 * pillar carries a short name (the heading), a one-line description,
 * and an icon_key the Svelte layer maps to a lucide icon.
 *
 * icon_key vocabulary (V2 grammar): heart | shield | book | flame |
 * sun | moon. Unknown keys fall back to a generic dot in the Svelte
 * layer so the page never breaks on a new key.
 */
final readonly class AboutPillar
{
    public function __construct(
        private string $name,
        private string $description,
        private string $iconKey,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('AboutPillar name cannot be empty');
        }
        if (trim($description) === '') {
            throw new InvalidArgumentException('AboutPillar description cannot be empty');
        }
        if (trim($iconKey) === '') {
            throw new InvalidArgumentException('AboutPillar icon_key cannot be empty');
        }
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): string
    {
        return $this->description;
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
            'name' => $this->name,
            'description' => $this->description,
            'icon_key' => $this->iconKey,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            name: (string) ($row['name'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            iconKey: (string) ($row['icon_key'] ?? ''),
        );
    }
}