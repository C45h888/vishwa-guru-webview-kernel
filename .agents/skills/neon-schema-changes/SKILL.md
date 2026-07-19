---
name: neon-schema-changes
description: >-
  Use when you need to apply, verify, or report on Neon PostgreSQL schema
  changes — running migrations against a Neon branch (production or
  feature), applying raw SQL via the Neon connection string, syncing
  Laravel's migrations table with reality, or producing a canonical-surface
  verification report. Captures the MCP-equivalent CLI workflow, the
  doctrine-correct Laravel path, and the verification probes that prove
  the link between code and database. Distinct from `deploy-v1-schema`
  (which is the first-time V1 bootstrap drill); this skill is for ongoing
  schema work after V1 lands.
---

# Neon Schema Changes — apply, verify, report

The operational drill for landing schema changes on a Neon PostgreSQL
branch from inside the temple-trust-webview-kernel repository, with the
doctrine-correct Laravel migration path as the primary route and the
MCP-equivalent Neon CLI as the runtime surface.

This skill captures the methodology that landed in the
`2026-07-18/19` Phase 2 thread: V1 schema application + queue
substrate + canonical surface verification.

**Use this skill when:**

- applying a Laravel migration to a Neon branch (production or feature)
- applying raw SQL via `psql` or Node + pg against Neon
- reconciling the Laravel `migrations` table with reality after a
  raw-SQL apply
- producing a canonical-surface verification report (tables, FKs,
  checks, indexes, enums, extensions)
- diagnosing "migration says it ran but the table doesn't exist"
- writing a probe-style JSON report mirroring
  `phase-0.5-validation-report.json`

**Do NOT use this skill for:**

- First-time V1 bootstrap. Use `deploy-v1-schema` instead — that
  skill captures the V1-specific pitfalls (btree_gist, IMMUTABLE
  predicates, deferred FKs, partial unique indexes) that this skill
  treats as background.
- Local Docker Postgres. This skill assumes Neon.
- Schema *changes* that don't need a Neon branch (local sqlite tests).

## 1. Pre-flight

1. Confirm `.neon` is linked. `cat .neon` should show `orgId` and
   `projectId`. If missing or stale, see `neon-postgres` skill for
   `npx neon@latest link` / `set-context`.

2. Confirm the target branch. Use `npx neon@latest branches list`
   to see the branches on the project. Production is typically
   `br-<random>` with `default: true`. Feature/phase branches
   should be created with `npx neon@latest branches create`.

3. Confirm the `.env.local` is present and current. It should
   contain `DATABASE_URL` (pooled) and `DATABASE_URL_UNPOOLED`
   (direct) for the target branch. If missing or stale:

       npx neon@latest env pull

   `.env.local` is gitignored (see `.gitignore`).

4. Decide the doctrine path. Two routes, in order of preference:

       (a) Laravel migration: `php artisan migrate --force`
           - Doctrine-correct (every schema modification uses
             Laravel migrations per architecture.md).
           - Requires the Laravel boot to succeed — see Gap 1 below.
           - Records in the `migrations` table automatically.

       (b) Raw SQL via Node + pg:
           - Use when doctrine path is broken (boot crash, Postgres-
             specific grammar limitations, emergency fix).
           - You MUST manually insert a `migrations` row afterwards so
             Laravel knows the schema was applied.

## 2. Workflow — doctrine-correct path

```
# 2.1 — pin branch context (non-interactive)
CI= npx neon@latest set-context \
  --project-id purple-lab-70523539 \
  --org-id org-winter-dream-04162313 \
  --branch-id br-shiny-poetry-aow2d8mt

# 2.2 — fetch connection strings (pooled + direct)
CI= npx neon@latest env pull

# 2.3 — confirm boot path is healthy
docker run --rm -v $(pwd):/app -w /app temple-trust/runtime:php8.3 \
  php -r "require 'vendor/autoload.php'; \$app = require 'bootstrap/app.php';
          \$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
          echo 'BOOT OK'.PHP_EOL;"

# 2.4 — apply migrations
docker run --rm -v $(pwd):/app -w /app temple-trust/runtime:php8.3 \
  php artisan migrate --force

# 2.5 — verify
docker run --rm -v $(pwd):/app -w /app temple-trust/runtime:php8.3 \
  php scripts/phase-2-queue-probes.php
```

If 2.3 fails: see Gap 1 (the most common boot crash is the
`Queue::connection()->getQueueManager()` expression that doesn't
exist on any concrete queue class). Fix in
`app/Runtime/Providers/RuntimeServiceProvider.php` — replace with
`$app->make(QueueManager::class)`.

If 2.4 fails with `current transaction is aborted`: see Gap 2.

If 2.4 fails because a `migrations` config array instead of string:
see Gap 3.

## 3. Workflow — raw SQL + manual record

When the doctrine path is unavailable, apply via Node + pg and
insert a migrations row to keep Laravel's state coherent.

```js
// /tmp/apply-schema.js
const { Client } = require('/tmp/node_modules/pg');
const c = new Client({ connectionString: process.env.PHASE_URL });
await c.connect();
await c.query('BEGIN');
await c.query(<SQL>);
await c.query('COMMIT');

// Mark Laravel migration as applied
await c.query(`
  INSERT INTO migrations (migration, batch)
  VALUES ('<migration_filename>', <batch>)
  ON CONFLICT DO NOTHING
`);
```

Then:

```
PHASE_URL=... node /tmp/apply-schema.js
```

## 4. Verification probes

Every apply needs a probe. The shape mirrors
`phase-0.5-validation-report.json`:

```json
{
  "branch": "...",
  "ran_at": "...",
  "phase": "...",
  "total": N,
  "passed": N,
  "failed": 0,
  "probes": [
    {"id": "r01", "name": "...", "status": "pass", ...}
  ]
}
```

Reference implementations:

- `scripts/phase-2-redis-probes.php` (Redis substrate)
- `scripts/phase-2-queue-probes.php` (queue substrate)
- `scripts/phase-2-canonical-surface.php` (the link itself)

The canonical surface probe verifies:

```
- user_tables, foreign_keys, check_constraints, indexes, enum_types
- extensions present: pgcrypto, citext, btree_gist
- migrations table contents
- queue substrate: failed_jobs, jobs, job_batches
- linked_files: .neon, .env.local, config/database.php, etc.
- link_status: CANONICAL | DEGRADED
```

## 5. Pitfalls — known classes that block Neon applies on Laravel 10

### Gap 1 — Runtime boot crash (RuntimeServiceProvider.php:76)

The `KernelSnapshotFactory` binding uses
`Queue::connection()->getQueueManager()` which doesn't exist on any
concrete queue class (`Illuminate\Contracts\Queue\Queue` has no
`getQueueManager()` method). Boot fails on every artisan command
that resolves the snapshot.

**Fix:** replace with `$app->make(QueueManager::class)`.

### Gap 2 — Postgres transaction aborted on duplicate-table

If a migration runs successfully for some tables then fails on a
later one, Postgres aborts the wrapping transaction. The next
migration attempt sees "current transaction is aborted" until you
either commit or rollback explicitly. If you're using
`Schema::create(...)` and the table already exists, you'll get this.

**Fix:** check existence first; use `Schema::createIfNotExists` if
appropriate; or restructure to not depend on ordering.

### Gap 3 — `database.migrations` config drift

Laravel 10 expects `database.migrations` to be a **string**
(`'migrations' => 'migrations'`). Laravel 11+ accepts an array
(`['table' => 'migrations', 'update_date_on_publish' => true]`).
If your config has the array shape but you're running Laravel 10,
`MigrationServiceProvider` reads the whole array and passes it as
the table name. `hasTable($table)` then calls
`explode('.', $table)` on an array → fatal error.

**Fix:** use the string shape for Laravel 10. `'update_date_on_publish'`
is a Laravel 11+ feature — don't backport its config shape.

### Gap 4 — Postgres 16 unique-constraint double-creation

Laravel's `Schema::create('failed_jobs', function ($t) {
$t->string('uuid')->unique(); })` generates CREATE TABLE with an
inline UNIQUE column constraint followed by `ALTER TABLE ...
ADD CONSTRAINT ... UNIQUE (uuid)`. Postgres rejects the second
one ("constraint already exists") and aborts the transaction.

**Fix:** detect at runtime, use raw DDL for postgres path:

```php
if (DB::connection()->getDriverName() === 'pgsql') {
    DB::statement('CREATE TABLE failed_jobs (...)');
    DB::statement('CREATE UNIQUE INDEX failed_jobs_uuid_unique ON failed_jobs (uuid)');
} else {
    Schema::create('failed_jobs', function ($t) { /* ... */ });
}
```

### Gap 5 — sqlite migration runs against postgres

Laravel runs every pending migration against the active connection
regardless of intent. If you have a `xxx_create_xyz_sqlite.php`
migration that uses `Schema::create('xyz', ...)`, it will try to
create `xyz` on postgres too, which either duplicates tables
already created by the postgres migration or fails on
postgres-specific grammar differences.

**Fix:** make the migration connection-aware:

```php
public function up(): void
{
    if (DB::connection()->getDriverName() !== 'sqlite') {
        return;
    }
    Schema::create('xyz', function ($t) { /* ... */ });
}
```

## 6. Operational notes — captured 2026-07-18/19

- The Neon CLI is authenticated via the API key in
  `~/.config/neonctl/credentials.json`. The CLI is the
  MCP-equivalent surface for what an agent without OAuth can do.
  The MCP server (`https://mcp.neon.tech/mcp`, HTTP transport,
  configured in `.mcp.json`) exposes the same operations as tools
  (`run_sql`, `list_branches`, `get_connection_string`, etc.)
  once an API key is wired in.

- `set-context` is deprecated; use `link` (with `--agent` for
  non-interactive) or `checkout`. Both verify inputs.

- `env pull` writes to `.env.local` by default; the CLI handles
  auth via the existing credentials file. No OAuth needed for
  `env pull` once the credentials are in place.

- The pooled hostname has `-pooler` in the DNS suffix; the unpooled
  doesn't. Use pooled for serverless / high-concurrency; use
  unpooled for migrations (which need long-lived prepared statements).

- The Neon `pg_dump` tool can dump a branch's schema, useful for
  diffing against another branch's canonical schema:

      npx neon@latest branches list
      npx neon@latest psql <branch> --command '\dt' --output table

- Probe scripts should always set exit code 0 on full pass,
  non-zero on any failure. CI tooling depends on this.

## 7. Verification report shape (canonical)

The canonical surface probe answers five questions:

1. What branch / project / region am I looking at?
2. What's the count of canonical surface objects (tables, FKs, etc.)?
3. What migrations has Laravel recorded?
4. What files participate in the link (config, providers, migrations)?
5. Is the link CANONICAL or DEGRADED?

Reference output:

```json
{
  "phase": "2 — Platform Foundation",
  "branch": "production (br-shiny-poetry-aow2d8mt)",
  "project": "purple-lab-70523539",
  "canonical_surface": {
    "user_tables": 25,
    "foreign_keys": 28,
    "check_constraints": 248,
    "indexes": 78,
    "enum_types": 14,
    "extensions": ["btree_gist@1.8", "citext@1.8", "pgcrypto@1.4"]
  },
  "queue_substrate": {
    "tables": ["failed_jobs", "job_batches", "jobs"],
    "driver": "redis (DB 2)",
    "failed_retention_h": 720
  },
  "migrations_recorded": [...],
  "link_status": "CANONICAL"
}
```

## 8. Rollback

There is no Laravel-level rollback for raw-SQL applies — the
postgres migration's `down()` is intentionally empty per the
doctrine ("this migration is one-way — use the Neon branch reset
to undo"). If you need to revert a schema change on a production
branch:

1. Create a new branch off `production` with
   `npx neon@latest branches create --name revert-<timestamp>`.
2. Apply the inverse DDL via Node + pg.
3. Record the inverse migration in `migrations`.
4. Test on the revert branch.
5. Promote: `npx neon@latest set-context --branch-id <revert-branch>`.
6. Production now points at the revert branch.

For irreversible damage (data loss), reset the branch:

      npx neon@latest branches delete <branch-name>

This destroys compute + storage. Not reversible.

## 9. Related skills

- `deploy-v1-schema` — first-time V1 bootstrap, including V1-specific
  pitfalls (btree_gist, IMMUTABLE predicates, deferred FKs).
- `neon-postgres` — Neon platform overview, setup, connection
  methods, CLI/MCP/API surfaces, branching, autoscaling, pooling.
- `neon` — Neon platform overview covering Auth, Functions, Storage,
  AI Gateway, and the broader backend-for-apps surface.

## 10. Doctrine compliance self-check

| Doctrine (AGENTS.md / architecture.md) | Compliance |
|---|---|
| Financial integrity takes precedence | ✓ — failed_jobs is forensic, never silently dropped |
| Security over speed | ✓ — TLS required on Neon, sslmode=require |
| Maintainability over cleverness | ✓ — explicit probe reports, no hidden state |
| Framework conventions over abstractions | ✓ — Laravel migration path is primary |
| Explicit workflows over implicit | ✓ — every change leaves a probe report |
| Readable code over compact | ✓ — probe scripts print JSON, not one-line summaries |
| Business logic in services | ✓ — migration is operational, not business logic |
| Schema modifications through Laravel migrations | ✓ — primary route is doctrine-correct |
| Secrets in env config only | ✓ — DATABASE_URL in .env.local, gitignored |
| Avoid unnecessary dependencies | ✓ — uses Node pg (already needed) |
| Avoid premature optimization | ✓ — raw SQL only when doctrine path is broken |
