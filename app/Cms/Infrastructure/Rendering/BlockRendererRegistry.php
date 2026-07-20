<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Rendering;

use App\Cms\Contracts\BlockRendererContract;
use App\Cms\Domain\ValueObjects\Blocks\Block;
use LogicException;

/**
 * Registry of BlockRendererContract implementations.
 *
 * Holds the registered renderers. `render(Block)` looks up the renderer
 * that `supports()` the given block and delegates to its `render()`.
 * Throws LogicException if no renderer claims the block — this is a
 * programming error (the kernel added a Block VO without registering
 * its renderer).
 *
 * Bound in CmsServiceProvider::register() with all 5 renderers
 * pre-registered.
 */
final class BlockRendererRegistry
{
    /**
     * @var list<BlockRendererContract>
     */
    private array $renderers = [];

    public function register(BlockRendererContract $renderer): void
    {
        $this->renderers[] = $renderer;
    }

    public function render(Block $block): string
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($block)) {
                return $renderer->render($block);
            }
        }

        throw new LogicException(
            sprintf('No BlockRenderer registered for block type: %s', $block->type())
        );
    }
}