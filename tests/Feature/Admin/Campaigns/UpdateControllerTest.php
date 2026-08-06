<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Campaigns;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * UpdateControllerTest — feature tests for the admin "edit campaign"
 * PUT handler.
 */
final class UpdateControllerTest extends TestCase
{
    use RefreshDatabase;

    private const CAMPAIGN_ID = '01EXISTING000000000000CAMPA0';

    protected function setUp(): void
    {
        parent::setUp();
        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        DB::table('currencies')->insert([
            'code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹',
            'minor_unit_digits' => 2, 'display_order' => 10, 'is_active' => 1,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('campaigns')->insert([
            'id' => self::CAMPAIGN_ID,
            'slug' => 'winter-fund',
            'title' => 'Winter Fund',
            'description' => null,
            'short_description' => null,
            'category' => 'general',
            'currency_code' => 'INR',
            'target_amount_minor' => null,
            'state' => 'draft',
            'starts_at' => null,
            'ends_at' => null,
            'display_order' => 0,
            'is_featured' => false,
            'cover_image_file_id' => null,
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
            'slug' => 'winter-fund',
            'title' => 'Winter Fund',
            'description' => 'Updated description',
            'short_description' => 'Short',
            'category' => 'general',
            'currency_code' => 'INR',
            'target_amount_minor' => '750000',
            'state' => 'active',
            'starts_at' => '',
            'ends_at' => '',
            'is_featured' => '1',
            'display_order' => '10',
            'cover_image_file_id' => '',
        ], $overrides);
    }

    #[Test]
    public function test_admin_can_update_a_campaign(): void
    {
        $this->actingAs($this->admin())
            ->put('/admin/campaigns/'.self::CAMPAIGN_ID, $this->validPayload())
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'id' => self::CAMPAIGN_ID,
            'state' => 'active',
            'is_featured' => true,
            'target_amount_minor' => 750000,
            'display_order' => 10,
        ]);
    }

    #[Test]
    public function test_404_on_missing_campaign(): void
    {
        // Use a unique slug so the unique-slug validator doesn't trip
        // before the controller's null-check (slug uniqueness only
        // matters when the row exists; for the 404 path we want the
        // repository's null return to be the source of the 404).
        $this->actingAs($this->admin())
            ->put('/admin/campaigns/01DOESNOTEXIST00000CAMPA0', $this->validPayload(['slug' => 'never-existed-slug']))
            ->assertNotFound();
    }

    #[Test]
    public function test_unauthenticated_redirects_to_login(): void
    {
        $this->put('/admin/campaigns/'.self::CAMPAIGN_ID, $this->validPayload())
            ->assertRedirect('/login');
    }
}
