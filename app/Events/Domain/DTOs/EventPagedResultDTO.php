<?php

declare(strict_types=1);

namespace App\Events\Domain\DTOs;

use InvalidArgumentException;

/**
 * Paged result envelope for public event lists.
 *
 * Returned by `EventsQueryContract::listPast()` and `listAll()`.
 * Wraps a page of `EventSummaryDTO` items along with the total count
 * and a derived `hasMore` flag.
 *
 * Doctrine: items is always a `list` (never null) — empty lists are
 * valid (no past events yet).
 */
final readonly class EventPagedResultDTO
{
    /**
     * @param  list<EventSummaryDTO>  $items
     */
    public function __construct(
        public array $items,
        public int $total,
        public int $page,
        public int $perPage,
        public bool $hasMore,
    ) {
        if ($total < 0) {
            throw new InvalidArgumentException("EventPagedResultDTO total cannot be negative, got: {$total}");
        }
        if ($page < 1) {
            throw new InvalidArgumentException("EventPagedResultDTO page must be >= 1, got: {$page}");
        }
        if ($perPage < 1) {
            throw new InvalidArgumentException("EventPagedResultDTO perPage must be >= 1, got: {$perPage}");
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items'    => array_map(static fn (EventSummaryDTO $d): array => $d->toArray(), $this->items),
            'total'    => $this->total,
            'page'     => $this->page,
            'per_page' => $this->perPage,
            'has_more' => $this->hasMore,
        ];
    }
}
