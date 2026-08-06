<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Persistence\Contracts\PersistenceAdapterContract;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * AdminSeeder — single canonical admin for the Temple Trust.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - The trust has ONE admin. No multi-admin management UI is built
 *     in Pass 1; this seeder is the canonical entry point.
 *   - Credentials come from env: ADMIN_EMAIL, ADMIN_PASSWORD,
 *     ADMIN_NAME. The defaults below are NON-CREDENTIAL placeholders
 *     so the seeder just works in local dev. Real production envs MUST
 *     override ADMIN_PASSWORD — see .env.testing for the test-env values
 *     and .env.local / .env for production.
 *   - Seeder is idempotent: re-running updates the password + role +
 *     name on the existing row rather than failing on UNIQUE (email).
 *     This is the behaviour `db:seed` expects.
 *   - The user is forced to role='admin' regardless of env input —
 *     Pass 1 has no other role.
 *   - Persistence goes through PersistenceAdapterContract, never DB::.
 *     Doctrine: repository boundary (matches ProductionSeeder).
 */
final class AdminSeeder extends Seeder
{
    /**
     * Dev-only placeholder credentials. NEVER use these in production —
     * .env / .env.local must override ADMIN_PASSWORD before running
     * `db:seed` against a production database.
     */
    private const DEFAULT_ADMIN_EMAIL = 'admin@vishwaguru.local';
    private const DEFAULT_ADMIN_PASSWORD = 'PLACEHOLDER_OVERRIDE_IN_ENV';
    private const DEFAULT_ADMIN_NAME = 'Temple Trust Admin';

    public function run(): void
    {
        $adapter = $this->container->make(PersistenceAdapterContract::class);

        $adminEmail = (string) env('ADMIN_EMAIL', self::DEFAULT_ADMIN_EMAIL);
        $adminPassword = (string) env('ADMIN_PASSWORD', self::DEFAULT_ADMIN_PASSWORD);
        $adminName = (string) env('ADMIN_NAME', self::DEFAULT_ADMIN_NAME);

        // Loud warning when the dev placeholder leaks into non-local env.
        if ($adminPassword === self::DEFAULT_ADMIN_PASSWORD
            && ! app()->environment(['local', 'testing'])) {
            $this->command->error(
                '[AdminSeeder] ABORT: ADMIN_PASSWORD not set and the dev placeholder would land. Set ADMIN_PASSWORD in .env before running db:seed in production.'
            );
            return;
        }

        $now = (new \DateTimeImmutable())->format(DATE_ATOM);
        $ulid = (string) Str::ulid();
        $bcrypt = password_hash($adminPassword, PASSWORD_BCRYPT);

        $r = $adapter->execute(
            'INSERT INTO users (
                id, name, email, email_verified_at, password,
                role, remember_token, created_at, updated_at
             ) VALUES (
                :id, :name, :email, :now, :password,
                :role, :remember, :now, :now
             )
             ON CONFLICT (email) DO UPDATE SET
                name              = EXCLUDED.name,
                password          = EXCLUDED.password,
                role              = EXCLUDED.role,
                email_verified_at = EXCLUDED.email_verified_at,
                updated_at        = EXCLUDED.updated_at',
            [
                'id'        => $ulid,
                'name'      => $adminName,
                'email'     => $adminEmail,
                'now'       => $now,
                'password'  => $bcrypt,
                'role'      => 'admin',
                'remember'  => Str::random(10),
            ],
        );

        if ($r->isFailure()) {
            $this->command->error('[AdminSeeder] upsert failed: '.$r->error());
            return;
        }

        $this->command->info('[AdminSeeder] OK — seeded '.$adminEmail);
    }
}
