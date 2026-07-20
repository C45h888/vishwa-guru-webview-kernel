<?php

declare(strict_types=1);

namespace App\Events\Services;

use App\Events\Contracts\EventsQueryContract;
use App\Events\Domain\DTOs\EventDetailDTO;
use App\Events\Domain\DTOs\EventPagedResultDTO;
use App\Events\Domain\DTOs\EventSummaryDTO;
use App\Events\Domain\Repositories\EventRepositoryContract;

/**
 * EventsQueryService — public read surface for temple events.
 *
 * Composes EventRepositoryContract. The `:now` timestamp is built once
 * per request from the system clock and passed as a bind parameter
 * (because PG rejects NOW() in partial-index predicates).
 *
 * Doctrine: thin service. V1 is read-only; admin mutations arrive in
 * Phase 4. Page limits: perPage clamped to [1, 50], default 12.
 * listUpcoming / listFeatured accept any positive limit.
 */
final class EventsQueryService implements EventsQueryContract
{
    private const DEFAULT_PER_PAGE = 12;

    private const MIN_PER_PAGE = 1;

    private const MAX_PER_PAGE = 50;

    public function __construct(
        private readonly EventRepositoryContract $events,
    ) {
    }

    public function listUpcoming(int $limit = 6): array
    {
        if ($limit < 1) {
            $limit = 1;
        }

        return $this->events->listUpcoming(self::nowIso(), $limit);
    }

    public function listPast(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): EventPagedResultDTO
    {
        [$page, $perPage] = self::clampPaging($page, $perPage);
        $offset = ($page - 1) * $perPage;
        $result = $this->events->listPast(self::nowIso(), $perPage, $offset);

        return new EventPagedResultDTO(
            items: $result['items'],
            total: $result['total'],
            page: $page,
            perPage: $perPage,
            hasMore: ($page * $perPage) < $result['total'],
        );
    }

    public function listFeatured(int $limit = 6): array
    {
        if ($limit < 1) {
            $limit = 1;
        }

        return $this->events->listFeatured($limit);
    }

    public function listAll(int $page = 1, int $perPage = self::DEFAULT_PER_PAGE): EventPagedResultDTO
    {
        [$page, $perPage] = self::clampPaging($page, $perPage);
        $offset = ($page - 1) * $perPage;
        $result = $this->events->listAll($perPage, $offset);

        return new EventPagedResultDTO(
            items: $result['items'],
            total: $result['total'],
            page: $page,
            perPage: $perPage,
            hasMore: ($page * $perPage) < $result['total'],
        );
    }

    public function findById(string $id): ?EventDetailDTO
    {
        return $this->events->findById($id);
    }

    public function findBySlug(string $slug): ?EventDetailDTO
    {
        return $this->events->findBySlug($slug);
    }

    private static function nowIso(): string
    {
        return (new \DateTimeImmutable())->format(DATE_ATOM);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function clampPaging(int $page, int $perPage): array
    {
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < self::MIN_PER_PAGE) {
            $perPage = self::DEFAULT_PER_PAGE;
        } elseif ($perPage > self::MAX_PER_PAGE) {
            $perPage = self::MAX_PER_PAGE;
        }

        return [$page, $perPage];
    }
}
