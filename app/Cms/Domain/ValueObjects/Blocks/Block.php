<?php

declare(strict_types=1);

namespace App\Cms\Domain\ValueObjects\Blocks;

/**
 * Contract for a block supported by the V1 page-body grammar.
 *
 * PHP has no sealed-interface syntax. The supported block grammar is
 * explicitly allowlisted by PageBody::buildBlock(); adding a V2 block
 * requires updating that factory, bumping PageBody::version, and adding
 * a corresponding BlockRenderer.
 */
interface Block
{
    /**
     * Discriminator for the block type — used by the BlockRendererRegistry
     * to dispatch to the correct renderer.
     */
    public function type(): string;
}