<?php

declare(strict_types=1);

namespace App\Cms\Domain\DTOs;

use App\Cms\Domain\Entities\HeroBanner;
use App\Cms\Domain\Entities\StaticPage;
use DateTimeImmutable;

/**
 * The fully assembled public page that the Phase 3 frontend renders.
 *
 * Contains the StaticPage entity, its resolved hero banners, its
 * resolved references (campaign payloads, etc.), the pre-rendered HTML,
 * and a timestamp indicating when this assembly was produced.
 */
final readonly class RenderedStaticPage
{
    /**
     * @param  list<HeroBanner>  $heroBanners
     * @param  list<ResolvedReference>  $resolvedReferences
     */
    public function __construct(
        public StaticPage $page,
        public array $heroBanners,
        public array $resolvedReferences,
        public string $html,
        public DateTimeImmutable $resolvedAt,
    ) {
    }
}