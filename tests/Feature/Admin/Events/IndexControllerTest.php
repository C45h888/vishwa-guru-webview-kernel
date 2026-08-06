<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Events;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * IndexControllerTest — admin events list.
 */
final class IndexControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_unauthenticated_redirects_to_login(): void
    {
        $response = $this->get('/admin/events');
        $response->assertRedirect('/login');
    }

    #[Test]
    public function test_admin_sees_all_events_including_drafts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        DB::table('events')->insert([
            'id' => '01TEST000000000000000EVENTU0',
            'slug' => 'varalakshmi-vratam',
            'title' => 'Varalakshmi Vratam',
            'timezone' => 'Asia/Kolkata',
            'starts_at' => '2026-09-15T09:00:00+00:00',
            'state' => 'published',
            'is_featured' => false,
            'display_order' => 0,
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('events')->insert([
            'id' => '01TEST000000000000000EVENTD0',
            'slug' => 'upcoming-event',
            'title' => 'Upcoming Event',
            'timezone' => 'Asia/Kolkata',
            'starts_at' => '2027-01-15T09:00:00+00:00',
            'state' => 'draft',
            'is_featured' => false,
            'display_order' => 0,
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->get('/admin/events');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('admin/events/Index')
                ->where('events', fn ($rows) => count($rows) === 2)
                ->where('pagination.total', 2)
        );
    }

    #[Test]
    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->role = 'editor';
        $this->actingAs($user);

        $response = $this->get('/admin/events');
        $response->assertForbidden();
    }
}
