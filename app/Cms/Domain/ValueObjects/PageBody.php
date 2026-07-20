<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects;

use App\Cms\Domain\ValueObjects\Blocks\Block;
use App\Cms\Domain\ValueObjects\Blocks\CtaButtonBlock;
use App\Cms\Domain\ValueObjects\Blocks\DividerBlock;
use App\Cms\Domain\ValueObjects\Blocks\HeadingBlock;
use App\Cms\Domain\ValueObjects\Blocks\ImageBlock;
use App\Cms\Domain\ValueObjects\Blocks\ParagraphBlock;
use InvalidArgumentException;

/**
 * Canonical representation of static_pages.body_json.
 *
 * The page body is a versioned block list. V1 carries grammar version 1
 * with 5 block types (paragraph, heading, image, cta_button, divider).
 * Adding a new block type requires:
 *   1. Implement the Block sealed interface
 *   2. Update the permits clause in Block.php
 *   3. Bump $version here
 *   4. Add a BlockRenderer for the new type
 *
 * The constructor validates every block by reconstructing typed Block
 * VOs from raw arrays. Unknown block types raise InvalidArgumentException
 * — this is a programming error, not a business exception; admin UI
 * must validate before persisting.
 */
final readonly class PageBody
{
    public const CURRENT_VERSION = 1;

    /**
     * @param  list<Block>  $blocks
     */
    public function __construct(
        private readonly int $version,
        private readonly array $blocks,
    ) {
        if ($version < 1) {
            throw new InvalidArgumentException(
                "PageBody version must be >= 1 (got {$version})"
            );
        }
        if ($version > self::CURRENT_VERSION) {
            throw new InvalidArgumentException(
                "PageBody version {$version} exceeds kernel-supported version "
                .self::CURRENT_VERSION.' — kernel upgrade required'
            );
        }
        foreach ($blocks as $i => $block) {
            if (! $block instanceof Block) {
                throw new InvalidArgumentException(
                    "PageBody block at index {$i} is not a Block instance"
                );
            }
        }
    }

    public function version(): int
    {
        return $this->version;
    }

    /**
     * @return list<Block>
     */
    public function blocks(): array
    {
        return $this->blocks;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'blocks' => array_map(static fn (Block $b) => $b->toArray(), $this->blocks),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $version = (int) ($row['version'] ?? self::CURRENT_VERSION);
        $rawBlocks = $row['blocks'] ?? [];
        if (! is_array($rawBlocks)) {
            throw new InvalidArgumentException('PageBody.blocks must be an array');
        }

        $blocks = [];
        foreach ($rawBlocks as $i => $raw) {
            if (! is_array($raw)) {
                throw new InvalidArgumentException("PageBody block at index {$i} must be an array");
            }
            $type = $raw['type'] ?? null;
            if (! is_string($type)) {
                throw new InvalidArgumentException("PageBody block at index {$i} missing 'type' field");
            }

            $blocks[] = self::buildBlock($type, $raw, $i);
        }

        return new self($version, $blocks);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    private static function buildBlock(string $type, array $raw, int $index): Block
    {
        return match ($type) {
            'paragraph' => new ParagraphBlock(
                text: (string) ($raw['text'] ?? ''),
            ),
            'heading' => new HeadingBlock(
                level: (int) ($raw['level'] ?? 1),
                text: (string) ($raw['text'] ?? ''),
            ),
            'image' => new ImageBlock(
                fileId: \App\Persistence\ValueObjects\EntityId::fromString((string) ($raw['file_id'] ?? '')),
                alt: (string) ($raw['alt'] ?? ''),
                caption: isset($raw['caption']) ? (string) $raw['caption'] : null,
                width: isset($raw['width']) ? (int) $raw['width'] : null,
                height: isset($raw['height']) ? (int) $raw['height'] : null,
            ),
            'cta_button' => new CtaButtonBlock(
                label: (string) ($raw['label'] ?? ''),
                url: (string) ($raw['url'] ?? ''),
                style: (string) ($raw['style'] ?? CtaButtonBlock::STYLE_PRIMARY),
                openInNewTab: (bool) ($raw['open_in_new_tab'] ?? false),
            ),
            'divider' => new DividerBlock(
                style: (string) ($raw['style'] ?? DividerBlock::STYLE_SOLID),
                width: (string) ($raw['width'] ?? DividerBlock::WIDTH_FULL),
            ),
            default => throw new InvalidArgumentException(
                "PageBody block at index {$index} has unknown type: {$type}"
            ),
        };
    }
}