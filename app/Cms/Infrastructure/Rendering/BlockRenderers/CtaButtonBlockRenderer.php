<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Rendering\BlockRenderers;

use App\Cms\Contracts\BlockRendererContract;
use App\Cms\Domain\ValueObjects\Blocks\Block;
use App\Cms\Domain\ValueObjects\Blocks\CtaButtonBlock;

final class CtaButtonBlockRenderer implements BlockRendererContract
{
    public function supports(Block $block): bool
    {
        return $block instanceof CtaButtonBlock;
    }

    public function render(Block $block): string
    {
        \assert($block instanceof CtaButtonBlock);

        $label = htmlspecialchars($block->label, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $url = htmlspecialchars($block->url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $style = htmlspecialchars($block->style, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $attrs = "href=\"{$url}\" class=\"cta cta--{$style}\"";
        if ($block->openInNewTab) {
            $attrs .= ' target="_blank" rel="noopener noreferrer"';
        }

        return "<a {$attrs}>{$label}</a>";
    }
}