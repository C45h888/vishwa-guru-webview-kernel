<?php

declare(strict_types=1);

namespace App\Cms\Contracts;

use App\Cms\Domain\ValueObjects\Blocks\Block;

/**
 * Contract for rendering a single Block VO to HTML.
 *
 * Implementations are typically registered in BlockRendererRegistry.
 * Each implementation owns its own HTML escaping; there is no central
 * sanitizer because escaping is renderer-specific.
 */
interface BlockRendererContract
{
    /**
     * Whether this renderer claims the given block.
     */
    public function supports(Block $block): bool;

    /**
     * Render the block to HTML.
     */
    public function render(Block $block): string;
}