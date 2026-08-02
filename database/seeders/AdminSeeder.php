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
 *     ADMIN_NAME. Defaults exist for local dev only and are flagged
 *     loudly so production cannot accidentally inherit them.
 *   - Seeder is idempotent: re-running updates the password and
 *     role on the existing row rather than duplicating. This is the
 *     behaviour the `db:seed` workflow expects.
 *   - The user is forced to role='admin' regardless of env input —
 *     Pass 1 has no other role to fall back to.
 *   - Persistence goes through PersistenceAdapterContract, never
 *     DB::. Doctrine: repository boundary (matches ProductionSeeder).
 */
final class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $adapter = $this->container->make(PersistenceAdapterContract::class);

        $adminEmail = (string) env('ADMIN_EMAIL', 'admin@vishwaguru.local');
        $adminPassword = (string) env('ADMIN_PASSWORD', 'changeme-admin-2026');
        $adminName = (string) env('ADMIN_NAME', 'Temple Trust Admin');

        if ($adminPassword === 'changeme-admin-2026') {
            // Surface a loud warning in any environment so this default is
            // never silently shipped. Tests run with APP_ENV=testing, so
            // this branch lights up locally; production should override.
            $this->command->warn(
                '[AdminSeeder] Using default ADMIN_PASSWORD. Override ADMIN_PASSWORD in .env for non-local environments.'
            );
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
        }
    }
}
