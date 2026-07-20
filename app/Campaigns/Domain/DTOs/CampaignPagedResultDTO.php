<?php

declare(strict_types=1);

namespace App\Campaigns\Domain\DTOs;

use InvalidArgumentException;

/**
 * Paged result envelope for public campaign lists.
 *
 * Returned by `CampaignsQueryContract::listDisplayable()`. Wraps a
 * page of `CampaignSummaryDTO` items along with the total count and
 * a derived `hasMore` flag so the UI can render pagination controls.
 *
 * Doctrine: items is always a `list` (never null) — empty lists are
 * valid (no displayable campaigns yet).
 */
final readonly class CampaignPagedResultDTO
{
    /**
     * @param  list<CampaignSummaryDTO>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public bool $hasMore,
    ) {
        if ($total < 0) {
            throw new InvalidArgumentException("CampaignPagedResultDTO total cannot be negative, got: {$total}");
        }
        if ($page < 1) {
            throw new InvalidArgumentException("CampaignPagedResultDTO page must be >= 1, got: {$page}");
        }
        if ($perPage < 1) {
            throw new InvalidArgumentException("CampaignPagedResultDTO perPage must be >= 1, got: {$perPage}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items'    => array_map(static fn (CampaignSummaryDTO $d): array => $d->toArray(), $this->items),
            'total'    => $this->total,
            'page'     => $this->page,
            'per_page' => $this->perPage,
            'has_more' => $this->hasMore,
        ];
    }
}
