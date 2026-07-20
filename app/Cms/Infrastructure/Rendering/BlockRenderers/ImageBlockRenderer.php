<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Rendering\BlockRenderers;

use App\Cms\Contracts\BlockRendererContract;
use App\Cms\Contracts\ImageUrlResolverContract;
use App\Cms\Domain\ValueObjects\Blocks\Block;
use App\Cms\Domain\ValueObjects\Blocks\ImageBlock;

final class ImageBlockRenderer implements BlockRendererContract
{
    public function __construct(
        private readonly ImageUrlResolverContract $urlResolver,
    ) {
    }

    public function supports(Block $block): bool
    {
        return $block instanceof ImageBlock;
    }

    public function render(Block $block): string
    {
        \assert($block instanceof ImageBlock);

        $alt = htmlspecialchars($block->alt, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $caption = $block->caption !== null
            ? htmlspecialchars($block->caption, ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : null;
        $src = htmlspecialchars(
            $this->urlResolver->resolve($block->fileId),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );

        $attrs = "src=\"{$src}\" alt=\"{$alt}\"";
        if ($block->width !== null) {
            $attrs .= ' width="'.((int) $block->width).'"';
        }
        if ($block->height !== null) {
            $attrs .= ' height="'.((int) $block->height).'"';
        }
        $attrs .= ' loading="lazy"';

        $img = "<img {$attrs}>";

        if ($caption !== null) {
            return "<figure>{$img}<figcaption>{$caption}</figcaption></figure>";
        }

        return "<figure>{$img}</figure>";
    }
}