<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Campaigns;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * StoreControllerTest — feature tests for the admin "create campaign"
 * POST handler. Doctrine: the controller is a thin shell over
 * CampaignAuthoringService, which is what we want to exercise end-to-end.
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
            'slug' => 'winter-fund',
            'title' => 'Winter Maintenance Fund',
            'description' => 'Cover the winter heating.',
            'short_description' => 'Heat for the temple.',
            'category' => 'general',
            'currency_code' => 'INR',
            'target_amount_minor' => '500000',
            'state' => 'draft',
            'starts_at' => '',
            'ends_at' => '',
            'is_featured' => '0',
            'display_order' => '0',
            'cover_image_file_id' => '',
        ];
    }

    #[Test]
    public function test_admin_can_create_a_campaign(): void
    {
        // Seed a currency so the FK validation passes.
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        DB::table('currencies')->insert([
            'code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹',
            'minor_unit_digits' => 2, 'display_order' => 10, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($this->admin())
            ->post('/admin/campaigns', $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'slug' => 'winter-fund',
            'title' => 'Winter Maintenance Fund',
            'state' => 'draft',
            'currency_code' => 'INR',
        ]);
    }

    #[Test]
    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->post('/admin/campaigns', $this->validPayload())
            ->assertRedirect('/login');
    }

    #[Test]
    public function test_duplicate_slug_redirects_back_with_error(): void
    {
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        DB::table('currencies')->insert([
            'code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹',
            'minor_unit_digits' => 2, 'display_order' => 10, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('campaigns')->insert([
            'id' => '01EXISTING000000000000CAMPA0',
            'slug' => 'winter-fund',
            'title' => 'Existing campaign',
            'category' => 'general',
            'currency_code' => 'INR',
            'state' => 'active',
            'is_featured' => false,
            'display_order' => 0,
            'metadata' => '{}',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($this->admin())
            ->from('/admin/campaigns/new')
            ->post('/admin/campaigns', $this->validPayload())
            ->assertRedirect('/admin/campaigns/new')
            ->assertSessionHasErrors('slug');
    }

    #[Test]
    public function test_invalid_state_value_fails_validation(): void
    {
        $payload = $this->validPayload();
        $payload['state'] = 'invalid-state';

        $this->actingAs($this->admin())
            ->post('/admin/campaigns', $payload)
            ->assertSessionHasErrors('state');
    }
}
