<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Events;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * EndControllerTest — admin "end event" POST handler.
 *
 * Doctrine:
 *   - Idempotent: posting /end to an already-completed event returns
 *     the existing row unchanged (completed_at is preserved).
 *   - 404 on missing event.
 *   - Logs the admin in.
 */
final class EndControllerTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT_ID = '01EXISTING00000000000EVENT0';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seedEvent(string $state = 'published', ?string $completedAt = null): void
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        DB::table('events')->insert([
            'id' => self::EVENT_ID,
            'slug' => 'varalakshmi-vratam',
            'title' => 'Varalakshmi Vratam',
            'timezone' => 'Asia/Kolkata',
            'starts_at' => '2026-09-15T09:00:00+00:00',
            'state' => $state,
            'completed_at' => $completedAt,
            'is_featured' => false,
            'display_order' => 0,
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    #[Test]
    public function test_admin_can_end_an_event(): void
    {
        $this->seedEvent('published');
        $this->actingAs($this->admin())
            ->post('/admin/events/'.self::EVENT_ID.'/end')
            ->assertRedirect();

        $row = DB::table('events')->where('id', self::EVENT_ID)->first();
        $this->assertSame('completed', $row->state);
        $this->assertNotNull($row->completed_at);
    }

    #[Test]
    public function test_ending_already_completed_event_is_idempotent(): void
    {
        $originalCompletedAt = '2026-09-15T18:00:00+00:00';
        $this->seedEvent('completed', $originalCompletedAt);

        $this->actingAs($this->admin())
            ->post('/admin/events/'.self::EVENT_ID.'/end')
            ->assertRedirect();

        $row = DB::table('events')->where('id', self::EVENT_ID)->first();
        // Doctrine: completed_at is preserved, not overwritten.
        $this->assertSame('completed', $row->state);
        $this->assertSame($originalCompletedAt, $row->completed_at);
    }

    #[Test]
    public function test_404_on_missing_event(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/events/01DOESNOTEXIST00000EVENT0/end')
            ->assertNotFound();
    }

    #[Test]
    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->seedEvent('published');
        $this->post('/admin/events/'.self::EVENT_ID.'/end')
            ->assertRedirect('/login');
    }
}
