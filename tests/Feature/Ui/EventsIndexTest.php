<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /events (events.Index).
 *
 * Seeds 2 upcoming + 1 past events and asserts both sections are
 * in the Inertia props with the expected pagination envelope.
 */
final class EventsIndexTest extends InfrastructureTestCase
{
    public function testIndexRendersUpcomingAndPastSections(): void
    {
        $this->seedEvent(
            id: 'evt_ui_up_1',
            state: 'published',
            title: 'Upcoming Festival',
            startsAt: (new DateTimeImmutable('+7 days'))->format(DATE_ATOM),
        );
        $this->seedEvent(
            id: 'evt_ui_up_2',
            state: 'published',
            title: 'Upcoming Yagna',
            startsAt: (new DateTimeImmutable('+30 days'))->format(DATE_ATOM),
        );
        $this->seedEvent(
            id: 'evt_ui_past_1',
            state: 'completed',
            title: 'Past Sankalpa',
            startsAt: (new DateTimeImmutable('-30 days'))->format(DATE_ATOM),
        );

        $response = $this->get('/events');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('events/Index')
            ->has('upcoming', 2)
            ->has('past', 1)
            ->has('pagination')
            ->etc()
        );
    }

    private function seedEvent(
        string $id,
        string $state,
        string $title,
        string $startsAt,
        ?string $endsAt = null,
        ?string $deletedAt = null,
    ): void {
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $this->adapter->execute(
            'INSERT INTO events (
                id, slug, title, state, starts_at, ends_at,
                timezone, is_featured, display_order, metadata,
                created_at, updated_at, deleted_at
            ) VALUES (
                :id, :slug, :title, :state, :starts, :ends,
                :tz, 0, 0, \'{}\',
                :created, :updated, :deleted
            )',
            [
                'id' => $id,
                'slug' => strtolower($id),
                'title' => $title,
                'state' => $state,
                'starts' => $startsAt,
                'ends' => $endsAt,
                'tz' => 'Asia/Kolkata',
                'created' => $now,
                'updated' => $now,
                'deleted' => $deletedAt,
            ],
        );
    }
}
