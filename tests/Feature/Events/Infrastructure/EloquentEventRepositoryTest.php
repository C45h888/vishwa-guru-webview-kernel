<?php

declare(strict_types=1);

namespace Tests\Feature\Events\Infrastructure;

use App\Events\Infrastructure\Repositories\EloquentEventRepository;
use DateTimeImmutable;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * Integration tests for EloquentEventRepository.
 *
 * Repository-level invariants tested here:
 *   - findById/findBySlug return only displayable rows (state IN
 *     published|completed) AND deleted_at IS NULL
 *   - listUpcoming filters on state='published' AND starts_at >= now
 *   - listPast filters on state IN (published,completed) AND starts_at < now
 *   - listFeatured respects is_featured
 *   - listAll honors paging
 */
final class EloquentEventRepositoryTest extends InfrastructureTestCase
{
    private EloquentEventRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentEventRepository($this->adapter);
    }

    public function testFindByIdReturnsDetailForPublishedEvent(): void
    {
        $this->seedEvent(id: 'event_01HPUB', state: 'published', title: 'Pub', daysFromNow: 30);
        $detail = $this->repo->findById('event_01HPUB');

        $this->assertNotNull($detail);
        $this->assertSame('event_01HPUB', $detail->id);
        $this->assertSame('published', $detail->state);
        $this->assertTrue($detail->isUpcoming);
    }

    public function testFindByIdReturnsNullForDraft(): void
    {
        $this->seedEvent(id: 'event_01HDRFT', state: 'draft', title: 'Draft', daysFromNow: 30);
        $this->assertNull($this->repo->findById('event_01HDRFT'));
    }

    public function testFindByIdReturnsNullForSoftDeleted(): void
    {
        $this->seedEvent(
            id: 'event_01HSOFTDEL',
            state: 'published',
            title: 'Soft',
            daysFromNow: 30,
            deletedAt: '2026-07-01T00:00:00+00:00',
        );
        $this->assertNull($this->repo->findById('event_01HSOFTDEL'));
    }

    public function testFindBySlugFindsDisplayableEvent(): void
    {
        $this->seedEvent(id: 'event_01HCOMP', state: 'completed', title: 'Done', slug: 'done-event', daysFromNow: -30);
        $detail = $this->repo->findBySlug('done-event');
        $this->assertNotNull($detail);
        $this->assertFalse($detail->isUpcoming); // completed past event
    }

    public function testListUpcomingFiltersOnStartsAtGteNow(): void
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->seedEvent(id: 'event_01HFUTURE', state: 'published', title: 'Future', daysFromNow: 10);
        $this->seedEvent(id: 'event_01HPAST', state: 'published', title: 'Past', daysFromNow: -10);
        $this->seedEvent(id: 'event_01HDRAFTFUTURE', state: 'draft', title: 'Draft Future', daysFromNow: 10);

        $upcoming = $this->repo->listUpcoming($now, 10);
        $this->assertCount(1, $upcoming);
        $this->assertSame('event_01HFUTURE', $upcoming[0]->id);
    }

    public function testListUpcomingExcludesDraftAndSoftDeleted(): void
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->seedEvent(id: 'event_01HPUB', state: 'published', title: 'Pub', daysFromNow: 5);
        $this->seedEvent(id: 'event_01HDRFT', state: 'draft', title: 'Draft', daysFromNow: 5);
        $this->seedEvent(
            id: 'event_01HSOFTDEL',
            state: 'published',
            title: 'Soft',
            daysFromNow: 5,
            deletedAt: '2026-07-01T00:00:00+00:00',
        );

        $upcoming = $this->repo->listUpcoming($now, 10);
        $this->assertCount(1, $upcoming);
        $this->assertSame('event_01HPUB', $upcoming[0]->id);
    }

    public function testListPastFiltersOnStartsAtLtNow(): void
    {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->seedEvent(id: 'event_01HPAST1', state: 'completed', title: 'Past 1', daysFromNow: -10);
        $this->seedEvent(id: 'event_01HPAST2', state: 'published', title: 'Past 2', daysFromNow: -5);
        $this->seedEvent(id: 'event_01HFUTURE', state: 'published', title: 'Future', daysFromNow: 5);

        $result = $this->repo->listPast($now, 12, 0);
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['items']);
        // DESC ordering: most recent past first
        $this->assertSame('event_01HPAST2', $result['items'][0]->id);
        $this->assertSame('event_01HPAST1', $result['items'][1]->id);
    }

    public function testListFeaturedRespectsFeaturedFlag(): void
    {
        $this->seedEvent(id: 'event_01HFA', state: 'published', title: 'A', daysFromNow: 30, isFeatured: true);
        $this->seedEvent(id: 'event_01HFB', state: 'published', title: 'B', daysFromNow: 30, isFeatured: false);

        $featured = $this->repo->listFeatured(10);
        $this->assertCount(1, $featured);
        $this->assertSame('event_01HFA', $featured[0]->id);
    }

    public function testListAllRespectsPagingAndTotal(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seedEvent(id: 'event_01HALL'.$i, state: 'published', title: 'A'.$i, daysFromNow: $i);
        }

        $page1 = $this->repo->listAll(2, 0);
        $this->assertSame(5, $page1['total']);
        $this->assertCount(2, $page1['items']);

        $page3 = $this->repo->listAll(2, 4);
        $this->assertSame(5, $page3['total']);
        $this->assertCount(1, $page3['items']);
    }

    private function seedEvent(
        string $id,
        string $state,
        string $title,
        int $daysFromNow,
        bool $isFeatured = false,
        ?string $deletedAt = null,
        ?string $slug = null,
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
                0, :featured, \'{}\',
                :created, :updated, :deleted
            )',
            [
                'id' => $id,
                'slug' => $slug ?? strtolower($id),
                'title' => $title,
                'state' => $state,
                'starts' => $starts->format('Y-m-d H:i:s'),
                'tz' => 'Asia/Kolkata',
                'featured' => $isFeatured ? 1 : 0,
                'created' => $now->format(DATE_ATOM),
                'updated' => $now->format(DATE_ATOM),
                'deleted' => $deletedAt,
            ],
        );
    }
}
