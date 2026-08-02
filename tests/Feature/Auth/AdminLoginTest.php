<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AdminLoginTest — feature tests for the canonical admin login flow.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - The auth surface is login + logout only. Register, forgot-password,
 *     reset-password, email-verification are INTENTIONALLY absent — see
 *     routes/auth.php for the rationale.
 *   - All assertions go through the HTTP kernel (no bypassing auth
 *     to test components in isolation). Tests use the SQLite in-memory
 *     backend via RefreshDatabase.
 *   - The factory's DEFAULT_PASSWORD is 'password' (see UserFactory);
 *     tests that need a known plaintext reference this constant.
 */
final class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_EMAIL = 'admin@vishwaguru.test';

    private const TEST_PASSWORD = 'password';   // mirrors UserFactory::DEFAULT_PASSWORD

    private function seedAdmin(): User
    {
        return User::factory()->create([
            'email' => self::TEST_EMAIL,
            'password' => Hash::make(self::TEST_PASSWORD),
            'role' => 'admin',
        ]);
    }

    #[Test]
    public function test_login_page_renders(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page->component('Auth/Login')
        );
    }

    #[Test]
    public function test_valid_credentials_login_and_redirect_to_admin(): void
    {
        $this->seedAdmin();

        $response = $this->post('/login', [
            'email' => self::TEST_EMAIL,
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertRedirect('/admin');
        $this->assertAuthenticatedAs(
            User::where('email', self::TEST_EMAIL)->first()
        );
    }

    #[Test]
    public function test_invalid_password_returns_validation_error(): void
    {
        $this->seedAdmin();

        $response = $this->post('/login', [
            'email' => self::TEST_EMAIL,
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function test_unknown_email_returns_same_generic_error(): void
    {
        // Doctrine: do NOT leak whether an email exists in the system.
        // Both unknown email and bad password surface the same
        // "auth.failed" message so the login form is a constant-time
        // oracle, not an account-enumeration oracle.
        $response = $this->post('/login', [
            'email' => 'nobody@example.test',
            'password' => 'irrelevant',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function test_throttled_after_five_failed_attempts(): void
    {
        $this->seedAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => self::TEST_EMAIL,
                'password' => 'wrong',
            ]);
        }

        // Sixth attempt — even with the correct password — is locked.
        $response = $this->post('/login', [
            'email' => self::TEST_EMAIL,
            'password' => self::TEST_PASSWORD,
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function test_login_redirects_already_authenticated_admin_to_dashboard(): void
    {
        $admin = $this->seedAdmin();
        $this->actingAs($admin);

        $response = $this->get('/login');

        // Laravel's `guest` middleware redirects authenticated users
        // away from /login. The default redirect target is
        // RouteServiceProvider::HOME; we send to /admin.
        $response->assertRedirect('/admin');
    }
}
