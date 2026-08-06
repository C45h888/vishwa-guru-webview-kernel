<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * AdminSeederTest — locks the canonical admin seed contract.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - The seeder reads ADMIN_EMAIL / ADMIN_PASSWORD / ADMIN_NAME from
 *     env. These tests run with APP_ENV=testing (phpunit.xml) so they
 *     use whatever values .env.testing sets.
 *   - The seeder is idempotent: re-running updates the existing row's
 *     password + role + name rather than failing on UNIQUE (email).
 *   - The role is always forced to 'admin' regardless of env input —
 *     Pass 1 has no other role.
 */
final class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_seeder_creates_admin_user_with_admin_role(): void
    {
        $this->seed(AdminSeeder::class);

        // At least one admin row must exist after seeding.
        $user = User::where('role', 'admin')->first();
        $this->assertNotNull($user, 'Admin user must be created.');
        $this->assertSame('admin', $user->role);
        $this->assertNotEmpty($user->name);
        $this->assertNotEmpty($user->password);
        $this->assertTrue(
            password_verify('changeme-test-2026', $user->password)
            || password_verify('changeme-admin-2026', $user->password)
            || strlen($user->password) >= 60,
            'Password must be bcrypt-hashed (60+ chars).'
        );
    }

    #[Test]
    public function test_seeder_is_idempotent_on_second_run(): void
    {
        $this->seed(AdminSeeder::class);
        $firstId = User::where('role', 'admin')->first()->id;

        $this->seed(AdminSeeder::class);
        $secondId = User::where('role', 'admin')->first()->id;

        // Same admin row → same primary key.
        $this->assertSame($firstId, $secondId);

        // Exactly one admin row exists.
        $this->assertSame(1, User::where('role', 'admin')->count());
    }
}
