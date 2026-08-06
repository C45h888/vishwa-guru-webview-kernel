<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Campaigns;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * IndexControllerTest — feature tests for the admin campaigns list.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - All assertions go through the HTTP kernel; we don't bypass
 *     the middleware to test the controller in isolation.
 *   - Tests use the SQLite in-memory backend via RefreshDatabase.
 *   - The list page shows ALL campaigns including drafts — the
 *     public surface still filters, but the admin sees everything.
 */
final class IndexControllerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_unauthenticated_redirects_to_login(): void
    {
        $response = $this->get('/admin/campaigns');
        $response->assertRedirect('/login');
    }

    #[Test]
    public function test_admin_sees_all_campaigns_including_drafts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        // One active campaign
        DB::table('campaigns')->insert([
            'id' => '01TEST000000000000000ACTIVE0',
            'slug' => 'temple-fund',
            'title' => 'Temple Fund',
            'category' => 'general',
            'currency_code' => 'INR',
            'state' => 'active',
            'is_featured' => false,
            'display_order' => 0,
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        // One draft campaign (must NOT appear on public, but should appear here)
        DB::table('campaigns')->insert([
            'id' => '01TEST00000000000000DRAFT00',
            'slug' => 'upcoming-event',
            'title' => 'Upcoming Event',
            'category' => 'festival',
            'currency_code' => 'INR',
            'state' => 'draft',
            'is_featured' => false,
            'display_order' => 0,
            'metadata' => '{}',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->get('/admin/campaigns');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('admin/campaigns/Index')
                ->where('campaigns', fn ($rows) => count($rows) === 2)
                ->where('pagination.total', 2)
        );
    }

    #[Test]
    public function test_non_admin_gets_403(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $user->role = 'editor';   // bypass DB CHECK for assertion
        $this->actingAs($user);

        $response = $this->get('/admin/campaigns');

        $response->assertForbidden();
    }
}
