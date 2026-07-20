<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Rendering\BlockRenderers;

use App\Cms\Contracts\BlockRendererContract;
use App\Cms\Domain\ValueObjects\Blocks\Block;
use App\Cms\Domain\ValueObjects\Blocks\HeadingBlock;

final class HeadingBlockRenderer implements BlockRendererContract
{
    public function supports(Block $block): bool
    {
        return $block instanceof HeadingBlock;
    }

    public function render(Block $block): string
    {
        \assert($block instanceof HeadingBlock);
        $escaped = htmlspecialchars($block->text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $level = max(1, min(6, $block->level));

        return "<h{$level}>{$escaped}</h{$level}>";
    }
}