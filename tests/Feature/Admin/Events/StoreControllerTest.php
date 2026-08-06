<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Events;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * StoreControllerTest — admin "create event" POST handler.
 */
final class StoreControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function validPayload(): array
    {
        return [
            'slug' => 'varalakshmi-vratam',
            'title' => 'Varalakshmi Vratam',
            'description' => 'Annual celebration of goddess Lakshmi.',
            'short_description' => 'Lakshmi celebration',
            'banner_file_id' => '',
            'starts_at' => '2026-09-15T09:00',
            'ends_at' => '2026-09-15T11:00',
            'timezone' => 'Asia/Kolkata',
            'venue' => 'Main Temple',
            'venue_address' => 'Temple Road, Hyderabad',
            'state' => 'draft',
            'is_featured' => '0',
            'display_order' => '0',
            'metadata' => [],
        ];
    }

    #[Test]
    public function test_admin_can_create_an_event(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/events', $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('events', [
            'slug' => 'varalakshmi-vratam',
            'title' => 'Varalakshmi Vratam',
            'state' => 'draft',
            'venue' => 'Main Temple',
        ]);
    }

    #[Test]
    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->post('/admin/events', $this->validPayload())
            ->assertRedirect('/login');
    }

    #[Test]
    public function test_duplicate_slug_redirects_back_with_error(): void
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        DB::table('events')->insert([
            'id' => '01EXISTING00000000000EVENT0',
            'slug' => 'varalakshmi-vratam',
            'title' => 'Existing event',
            'timezone' => 'Asia/Kolkata',
            'starts_at' => '2026-09-15T09:00:00+00:00',
            'state' => 'published',
            'is_featured' => false,
            'display_order' => 0,
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/events/new')
            ->post('/admin/events', $this->validPayload())
            ->assertRedirect('/admin/events/new')
            ->assertSessionHasErrors('slug');
    }

    #[Test]
    public function test_invalid_state_value_fails_validation(): void
    {
        $payload = $this->validPayload();
        $payload['state'] = 'invalid-state';

        $this->actingAs($this->admin())
            ->post('/admin/events', $payload)
            ->assertSessionHasErrors('state');
    }

    #[Test]
    public function test_starts_at_is_required(): void
    {
        $payload = $this->validPayload();
        unset($payload['starts_at']);

        $this->actingAs($this->admin())
            ->post('/admin/events', $payload)
            ->assertSessionHasErrors('starts_at');
    }
}
