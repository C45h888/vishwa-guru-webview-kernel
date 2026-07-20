<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Rendering\BlockRenderers;

use App\Cms\Contracts\BlockRendererContract;
use App\Cms\Domain\ValueObjects\Blocks\Block;
use App\Cms\Domain\ValueObjects\Blocks\ParagraphBlock;

final class ParagraphBlockRenderer implements BlockRendererContract
{
    public function supports(Block $block): bool
    {
        return $block instanceof ParagraphBlock;
    }

    public function render(Block $block): string
    {
        \assert($block instanceof ParagraphBlock);
        $escaped = htmlspecialchars($block->text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return "<p>{$escaped}</p>";
    }
}