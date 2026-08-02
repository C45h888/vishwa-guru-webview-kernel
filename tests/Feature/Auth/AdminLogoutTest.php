<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AdminLogoutTest — POST /logout clears the session and redirects home.
 *
 * Doctrine (mirrors Breeze 1.x's logout flow):
 *   - The session is invalidated AND the CSRF token is regenerated so
 *     a stolen cookie can't be replayed against a new session.
 *   - After logout the admin user is anonymous, so /admin redirects
 *     to /login. The logout endpoint itself redirects to /.
 */
final class AdminLogoutTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_authenticated_admin_can_logout(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $response = $this->post('/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    #[Test]
    public function test_after_logout_admin_dashboard_redirects_to_login(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        $this->post('/logout');

        $response = $this->get('/admin');
        $response->assertRedirect('/login');
    }
}
