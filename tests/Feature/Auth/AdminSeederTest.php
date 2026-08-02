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
 *   - The seeder reads ADMIN_EMAIL / ADMIN_PASSWORD / ADMIN_NAME from env.
 *     These tests run with APP_ENV=testing (phpunit.xml) so the defaults
 *     apply, but we override ADMIN_EMAIL to keep the assertion stable.
 *   - The seeder is idempotent: re-running with the same email updates
 *     the existing row's password + role + name rather than failing
 *     on UNIQUE (email).
 *   - The role is always forced to 'admin' regardless of env input —
 *     Pass 1 has no other role.
 */
final class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    private const SEED_EMAIL = 'seedtest@vishwaguru.test';

    private const SEED_PASSWORD = 'seeder-pass-2026';

    protected function setUp(): void
    {
        parent::setUp();

        // Override the env vars the seeder reads BEFORE the seeder
        // instance is constructed.
        putenv('ADMIN_EMAIL='.self::SEED_EMAIL);
        putenv('ADMIN_PASSWORD='.self::SEED_PASSWORD);
        putenv('ADMIN_NAME=Seeder Test Admin');
    }

    protected function tearDown(): void
    {
        // Restore to the suite default so other tests see the original env.
        putenv('ADMIN_EMAIL');
        putenv('ADMIN_PASSWORD');
        putenv('ADMIN_NAME');

        parent::tearDown();
    }

    #[Test]
    public function test_seeder_creates_admin_user_with_admin_role(): void
    {
        $this->seed(AdminSeeder::class);

        $user = User::where('email', self::SEED_EMAIL)->first();
        $this->assertNotNull($user, 'Admin user must be created.');
        $this->assertSame('admin', $user->role);
        $this->assertSame('Seeder Test Admin', $user->name);
        $this->assertTrue(Hash::check(self::SEED_PASSWORD, $user->password));
    }

    #[Test]
    public function test_seeder_is_idempotent_on_second_run(): void
    {
        $this->seed(AdminSeeder::class);
        $firstId = User::where('email', self::SEED_EMAIL)->first()->id;

        $this->seed(AdminSeeder::class);
        $secondId = User::where('email', self::SEED_EMAIL)->first()->id;

        // Same email → same row → same primary key.
        $this->assertSame($firstId, $secondId);

        // Exactly one admin row exists (no duplicates).
        $this->assertSame(1, User::where('email', self::SEED_EMAIL)->count());
    }
}
