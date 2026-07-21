<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use DateTimeImmutable;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Payments\Infrastructure\InfrastructureTestCase;

/**
 * HTTP-feature test for GET /events/{slug} (events.Show).
 */
final class EventsShowTest extends InfrastructureTestCase
{
    public function testShowRendersEventDetail(): void
    {
        $this->seedEvent(
            id: 'evt_show_1',
            state: 'published',
            title: 'Test Event',
            startsAt: (new DateTimeImmutable('+7 days'))->format(DATE_ATOM),
        );

        $response = $this->get('/events/evt_show_1');

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('events/Show')
            ->where('event.slug', 'evt_show_1')
            ->where('event.title', 'Test Event')
            ->etc()
        );
    }

    public function testShowReturns404ForMissingSlug(): void
    {
        $response = $this->get('/events/does-not-exist');

        $response->assertNotFound();
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
