<?php

declare(strict_types=1);

namespace App\Cms\Infrastructure\Rendering;

use App\Cms\Domain\ValueObjects\PageBody;

/**
 * StaticPageBodyRenderer — orchestrates block rendering.
 *
 * Iterates the PageBody's blocks, dispatches each to its renderer via
 * the BlockRendererRegistry, and concatenates the HTML with newline
 * separators. Returns the full HTML string.
 *
 * Output is HTML-escaped by each block renderer — there is no central
 * sanitizer because each renderer knows what its input requires.
 *
 * Stateless, side-effect-free. Invoked by StaticPageService on every
 * publish (per §5.6); the resulting HTML is stored in body_html.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §5.2.1
 */
final class StaticPageBodyRenderer
{
    public function __construct(
        private readonly BlockRendererRegistry $registry,
    ) {
    }

    public function render(PageBody $body): string
    {
        $parts = [];
        foreach ($body->blocks() as $block) {
            $parts[] = $this->registry->render($block);
        }

        return implode("\n", $parts);
    }
}