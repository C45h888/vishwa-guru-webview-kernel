<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects\Blocks;

/**
 * Sealed interface for the V1 block grammar.
 *
 * PHP 8.2+ sealed interfaces — only the listed permits can implement
 * this contract. Adding a new block type in V2 requires:
 *   1. New Block VO implementing this interface
 *   2. Update the permits clause
 *   3. Bump PageBody::version
 *   4. Add a BlockRenderer for the new type
 *
 * The permits list is the grammar itself.
 */
sealed interface Block permits ParagraphBlock, HeadingBlock, ImageBlock, CtaButtonBlock, DividerBlock
{
    /**
     * Discriminator for the block type — used by the BlockRendererRegistry
     * to dispatch to the correct renderer.
     */
    public function type(): string;
}