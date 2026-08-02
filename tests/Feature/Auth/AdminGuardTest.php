<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AdminGuardTest — the EnsureUserIsAdmin middleware gate on /admin.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - `/admin` is gated by THREE middleware: web, auth, admin.
 *   - `auth` redirects unauthenticated visitors to /login.
 *   - `admin` (EnsureUserIsAdmin) returns 403 to authenticated
 *     non-admin users. The two concerns are deliberately split
 *     so a logged-in non-admin sees the 403 page (not the login
 *     redirect, which they could already see).
 *   - Tests cover both halves of the split.
 */
final class AdminGuardTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_unauthenticated_admin_dashboard_redirects_to_login(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect('/login');
    }

    #[Test]
    public function test_admin_user_can_reach_admin_dashboard(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->get('/admin');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('admin/Dashboard')
        );
    }

    #[Test]
    public function test_non_admin_user_gets_403_on_admin_dashboard(): void
    {
        // Single canonical admin in V1, but the test guards the
        // middleware predicate against future multi-role expansion.
        // The role CHECK on the table only allows 'admin', so the
        // DB-stored role of a "non_admin" user is contrived — we
        // assert the middleware uses $user->isAdmin() by stubbing
        // the predicate.
        $nonAdmin = User::factory()->create(['role' => 'admin']);
        $nonAdmin->role = 'editor';   // bypass the DB CHECK for this assertion
        $this->actingAs($nonAdmin);

        $response = $this->get('/admin');

        $response->assertForbidden();
    }
}
