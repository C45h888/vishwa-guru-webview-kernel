<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Events;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UpdateControllerTest — admin "edit event" PUT handler.
 */
final class UpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    private const EVENT_ID = '01EXISTING00000000000EVENT0';

    protected function setUp(): void
    {
        parent::setUp();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        DB::table('events')->insert([
            'id' => self::EVENT_ID,
            'slug' => 'varalakshmi-vratam',
            'title' => 'Varalakshmi Vratam',
            'description' => null,
            'short_description' => null,
            'banner_file_id' => null,
            'starts_at' => '2026-09-15T09:00:00+00:00',
            'ends_at' => null,
            'timezone' => 'Asia/Kolkata',
            'venue' => null,
            'venue_address' => null,
            'state' => 'draft',
            'is_featured' => false,
            'display_order' => 0,
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'varalakshmi-vratam',
            'title' => 'Varalakshmi Vratam',
            'description' => 'Updated description',
            'short_description' => 'Short',
            'banner_file_id' => '',
            'starts_at' => '2026-09-15T09:00',
            'ends_at' => '2026-09-15T11:00',
            'timezone' => 'Asia/Kolkata',
            'venue' => 'Main Temple',
            'venue_address' => 'Temple Road',
            'state' => 'published',
            'is_featured' => '1',
            'display_order' => '10',
            'metadata' => [],
        ], $overrides);
    }

    #[Test]
    public function test_admin_can_update_an_event(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/events/'.self::EVENT_ID, $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'id' => self::EVENT_ID,
            'state' => 'published',
            'is_featured' => 1,
            'display_order' => 10,
            'venue' => 'Main Temple',
        ]);
    }

    #[Test]
    public function test_404_on_missing_event(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/events/01DOESNOTEXIST00000EVENT0', $this->validPayload(['slug' => 'never-existed-event']))
            ->assertNotFound();
    }

    #[Test]
    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->put('/admin/events/'.self::EVENT_ID, $this->validPayload())
            ->assertRedirect('/login');
    }
}
