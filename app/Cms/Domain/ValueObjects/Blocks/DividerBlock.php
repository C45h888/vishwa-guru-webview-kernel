<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects\Blocks;

use InvalidArgumentException;

/**
 * A divider block — rendered as `<hr>` with optional style + width.
 */
final readonly class DividerBlock implements Block
{
    public const STYLE_SOLID = 'solid';
    public const STYLE_DASHED = 'dashed';
    public const STYLE_DOTTED = 'dotted';

    public const WIDTH_FULL = 'full';
    public const WIDTH_HALF = 'half';
    public const WIDTH_QUARTER = 'quarter';

    private const STYLES = [
        self::STYLE_SOLID,
        self::STYLE_DASHED,
        self::STYLE_DOTTED,
    ];

    private const WIDTHS = [
        self::WIDTH_FULL,
        self::WIDTH_HALF,
        self::WIDTH_QUARTER,
    ];

    public function __construct(
        public string $style = self::STYLE_SOLID,
        public string $width = self::WIDTH_FULL,
    ) {
        if (! in_array($style, self::STYLES, true)) {
            throw new InvalidArgumentException(
                "DividerBlock style must be one of [solid, dashed, dotted] (got {$style})"
            );
        }
        if (! in_array($width, self::WIDTHS, true)) {
            throw new InvalidArgumentException(
                "DividerBlock width must be one of [full, half, quarter] (got {$width})"
            );
        }
    }

    public function type(): string
    {
        return 'divider';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'style' => $this->style,
            'width' => $this->width,
        ];
    }
}