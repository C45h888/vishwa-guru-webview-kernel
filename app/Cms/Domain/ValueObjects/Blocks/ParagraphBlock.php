<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects\Blocks;

/**
 * A paragraph block — plain text rendered inside `<p>` tags.
 *
 * HTML escaping is the renderer's responsibility; the block stores raw
 * text only.
 */
final readonly class ParagraphBlock implements Block
{
    public function __construct(
        public string $text,
    ) {
    }

    public function type(): string
    {
        return 'paragraph';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'text' => $this->text,
        ];
    }
}