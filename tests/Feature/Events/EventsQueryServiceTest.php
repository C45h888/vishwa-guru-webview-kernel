<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Events\Infrastructure\Repositories\EloquentEventRepository;
use App\Events\Services\EventsQueryService;
use DateTimeImmutable;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Service-level tests for EventsQueryService.
 *
 * Service-level invariants tested here:
 *   - listUpcoming defaults to limit 6 (when not specified)
 *   - listPast / listAll clamp perPage to [1, 50]
 *   - findById / findBySlug delegate correctly
 */
final class EventsQueryServiceTest extends InfrastructureTestCase
{
    private EventsQueryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EventsQueryService(
            new EloquentEventRepository($this->adapter),
        );
    }

    public function testListUpcomingDefaultsToLimitSix(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->seedEvent(id: 'event_01HUPC'.$i, state: 'published', title: 'U'.$i, daysFromNow: $i + 1);
        }

        $upcoming = $this->service->listUpcoming();
        $this->assertCount(6, $upcoming);
    }

    public function testListUpcomingRespectsCustomLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedEvent(id: 'event_01HUPC2'.$i, state: 'published', title: 'U'.$i, daysFromNow: $i + 1);
        }

        $upcoming = $this->service->listUpcoming(3);
        $this->assertCount(3, $upcoming);
    }

    public function testListPastClampsPerPageUpper(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->seedEvent(id: 'event_01HPST'.$i, state: 'completed', title: 'P'.$i, daysFromNow: -($i + 1));
        }
        $page = $this->service->listPast(1, 200);
        $this->assertSame(50, $page->perPage);
    }

    public function testListPastBumpsPageBelowOne(): void
    {
        $page = $this->service->listPast(0, 12);
        $this->assertSame(1, $page->page);
    }

    public function testFindBySlugReturnsNullForMissing(): void
    {
        $this->assertNull($this->service->findBySlug('nonexistent'));
    }

    public function testFindByIdReturnsDetailForPublished(): void
    {
        $this->seedEvent(id: 'event_01HPUB', state: 'published', title: 'Pub', daysFromNow: 10);
        $detail = $this->service->findById('event_01HPUB');
        $this->assertNotNull($detail);
        $this->assertSame('event_01HPUB', $detail->id);
    }

    public function testFindByIdReturnsNullForDraft(): void
    {
        $this->seedEvent(id: 'event_01HDRFT', state: 'draft', title: 'Draft', daysFromNow: 10);
        $this->assertNull($this->service->findById('event_01HDRFT'));
    }

    public function testListAllClampsPerPage(): void
    {
        $this->seedEvent(id: 'event_01HALL', state: 'published', title: 'A', daysFromNow: 5);
        $page = $this->service->listAll(1, 100);
        $this->assertSame(50, $page->perPage);
    }

    private function seedEvent(
        string $id,
        string $state,
        string $title,
        int $daysFromNow,
        ?string $deletedAt = null,
    ): void {
        $now = new DateTimeImmutable();
        $starts = $now->modify("{$daysFromNow} days");
        $this->adapter->execute(
            'INSERT INTO events (
                id, slug, title, state, starts_at, timezone,
                display_order, is_featured, metadata,
                created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :state, :starts, :tz,
                0, 0, \'{}\',
                :created, :updated, :deleted
            )',
            [
                'id' => $id,
                'slug' => strtolower($id),
                'title' => $title,
                'state' => $state,
                'starts' => $starts->format('Y-m-d H:i:s'),
                'tz' => 'Asia/Kolkata',
                'created' => $now->format(DATE_ATOM),
                'updated' => $now->format(DATE_ATOM),
                'deleted' => $deletedAt,
            ],
        );
    }
}
