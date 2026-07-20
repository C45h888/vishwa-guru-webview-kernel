<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects\Blocks;

use InvalidArgumentException;

/**
 * A heading block — level 1-6, rendered as `<hN>{text}</hN>`.
 */
final readonly class HeadingBlock implements Block
{
    public function __construct(
        public int $level,
        public string $text,
    ) {
        if ($level < 1 || $level > 6) {
            throw new InvalidArgumentException(
                "HeadingBlock level must be 1-6 (got {$level})"
            );
        }
        if (trim($text) === '') {
            throw new InvalidArgumentException('HeadingBlock text cannot be empty');
        }
    }

    public function type(): string
    {
        return 'heading';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type(),
            'level' => $this->level,
            'text' => $this->text,
        ];
    }
}