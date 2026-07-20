<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Rendering\BlockRenderers;

use App\Cms\Contracts\BlockRendererContract;
use App\Cms\Domain\ValueObjects\Blocks\Block;
use App\Cms\Domain\ValueObjects\Blocks\DividerBlock;

final class DividerBlockRenderer implements BlockRendererContract
{
    public function supports(Block $block): bool
    {
        return $block instanceof DividerBlock;
    }

    public function render(Block $block): string
    {
        \assert($block instanceof DividerBlock);
        $style = htmlspecialchars($block->style, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $width = htmlspecialchars($block->width, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return "<hr class=\"divider divider--{$style} divider--{$width}\">";
    }
}