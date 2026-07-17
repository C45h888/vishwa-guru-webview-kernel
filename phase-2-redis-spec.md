# Phase 2 — Redis Implementation Spec

## Purpose

Establish Redis as a first-class runtime dependency of the Temple Trust
Management System. This spec defines how Redis is installed, configured,
secured, observed, and used by the application, in alignment with the
constitutional doctrine.

The framework serves the architecture, not the other way around. Redis
exists to accelerate business workflows whose correctness remains anchored
in PostgreSQL. Redis is never the authoritative store.

This spec is the executable companion to the Phase 2 objective defined in
`roadmap.md` (lines 275-295). It closes the "Redis integration" deliverable
inside Phase 2.

---

## Client Decision

**Decision:** ext-phpredis (PECL `redis` extension, written in C,
hiredis-backed, 6.3.0 stable as of November 2025).

**Rationale:**

1. Team familiarity — primary driver, owned as preference.
2. Laravel-default alignment — `config/database.php` already declares
   `REDIS_CLIENT=phpredis`; matching the default removes one env override.
3. RESP3-readiness — phpredis speaks RESP3 via the `HELLO` handshake.
   RESP3 unlocks sharded pub/sub and client-side caching for future
   expansion without a client swap.
4. Feature parity with modern Redis — vector sets, hash field
   expiration, Valkey DELIFEQ land in phpredis first.
5. Performance delta is real but not load-bearing at current scale;
   it is a future-proofing argument, not an architectural one.

**Honest framing (for the commit message and module-inventory):**

  "phpredis chosen for team familiarity and RESP3-readiness. Performance
  delta is real (~2-5x on raw GET/SET) but not the deciding factor at
  current scale; the deciding factor is forward compatibility with the
  Redis 7+ feature surface and alignment with Laravel's recommended
  client."

**What this commits us to:**

- Every runtime environment must have `ext-redis` loaded.
- `php -m | grep redis` is a required boot-time check.
- `composer.json` MUST declare `ext-redis: *` in `require`.
- Docker base image MUST install the extension; CI MUST enable it.
- Windows contributors must use WSL or shuchkin DLL — no native
  Windows path is provided.

---

## Connection Topology

**Decision:** Single Redis 7+ instance for Phase 2. Hosted or self-hosted
choice is an operations decision, not an architectural one.

**Options on the table:**

| Option | When it applies | Phase 2 fit |
|--------|-----------------|-------------|
| `redis-server` in Docker Compose | Local dev + CI | ✓ |
| Self-hosted on the same host as Postgres | Single-server prod | ✓ |
| Hosted (Upstash, Redis Cloud, Aiven) | Multi-server prod | future |
| Sentinel (HA) | When uptime matters more than cost | future |
| Cluster | >25GB datasets or >100k ops/sec | not foreseeable |

**Phase 2 ships:** Docker Compose definition + production env config
shaped so a hosted endpoint can be swapped in via `REDIS_URL` without
code changes.

**Inter-agent linkage note:** The user's stated goal is connecting this
Redis to "other agents' infra". Two viable paths:

1. **Hosted Redis** with public/private network ACLs — cleanest cross-agent
   reach, costs money, requires TLS.
2. **Self-hosted Redis with public firewall** — cheaper, ops burden, no
   TLS-by-default.

Recommend option 1 (hosted) for inter-agent linkage. Phase 2 spec supports
either via `REDIS_URL`.

---

## Database Separation

Redis has 16 logical databases by default. Phase 2 separates concerns
across four logical DBs on the same instance:

| DB | Purpose | Laravel driver | Notes |
|----|---------|----------------|-------|
| 0 | App keys (locks, idempotency fast-path, webhook dedupe) | `default` | small, fast, frequent |
| 1 | Cache | `cache` | TTL-heavy, evictable |
| 2 | Queue | queue connection | durable enough for at-least-once |
| 3 | Session | session connection | Phase 4 admin auth |

**Why four DBs, not one:** observability. `MONITOR` per DB makes it
obvious which subsystem is misbehaving. Also lets ops flush cache
without nuking idempotency keys.

**Why not four separate instances:** cost + ops burden for negligible
isolation gain at current scale. One instance, four DBs.

---

## Key Prefix Strategy

**Decision:** Layered prefixes. Three levels:

1. **Global prefix** — `REDIS_PREFIX=temple_trust_` (already declared in
   `config/database.php`). Prevents collision with other apps on shared
   Redis (relevant for hosted Redis).
2. **Per-DB prefix** — Laravel adds automatically when configured. Keeps
   the four logical DBs visually separated in `KEYS *` output.
3. **Domain prefix** — explicit in our code, not magic:
   - `lock:donation:{id}` for state-transition locks
   - `idem:donation:{key}` for idempotency fast-path
   - `idem:webhook:{provider}:{event_id}` for webhook dedupe
   - `idem:api:{request_hash}` for Phase 3 API idempotency
   - `lock:receipt:{donation_id}` for receipt generation

Doctrine: domain boundaries must be explicit. Keys are visible in MONITOR
output and Redis Insight — the prefix tells the next engineer which
subsystem owns a key without grep-diving the codebase.

---

## Serialization

**Decision:** PHP native `serialize()` for Phase 2. Evaluate igbinary
in Phase 4 when cache pressure is measurable.

**Why not igbinary now:**

- Adds another PECL extension to install (ext-igbinary + ext-msgpack).
- ~3x smaller payloads is real but irrelevant at Phase 2 cache volumes.
- PHP serialize is reversible and debuggable (`unserialize(file_get_contents(...))`
  in tinker works).
- Doctrine: avoid premature optimization.

**When to revisit:** when Redis memory pressure becomes an ops concern,
or when payload sizes regularly exceed 4KB.

---

## Persistent Connections

**Decision:** Do NOT enable persistent connections in Phase 2.

**Why:**

- PHP-FPM's persistent connection model leaks state across requests
  unless every borrow re-issues `SELECT`. Easy to forget.
- Doctrine: financial integrity takes precedence. A stale connection
  leaking DB selection is a real risk.
- Modern PHP 8.2+ connect-to-Redis is sub-millisecond on warm
  connections kept alive by the server; the persistent-connect win
  on the client side is small.

**When to revisit:** if connection-establishment latency shows up in
profiles and we have proven (not assumed) we handle SELECT + AUTH
correctly on every borrow.

---

## Timeouts and Failure Semantics

**Decision:** Bounded timeouts everywhere. No retries. Fail-closed for
payment paths, fail-open for cache paths.

| Setting | Hot path (HTTP request) | Queue worker |
|---------|------------------------|--------------|
| `connect_timeout` | 1.5s | 5s |
| `read_timeout` | 0.5s | 30s |
| `retry_on_timeout` | false | true (Laravel's job retry) |
| `persistent` | false | false |

**Failure semantics:**

- **Payment path (idempotency check, webhook dedupe):** Redis down → fall
  through to Postgres (authoritative). Log warning. Do NOT block the
  request on Redis recovery.
- **Cache path:** Redis down → fall back to `file` cache automatically
  via Laravel's cache repository. Log warning.
- **Queue path:** Redis down → `queue:work` exits with non-zero; process
  supervisor (systemd / supervisord / Docker restart) restarts it. The
  queue itself is durable; the loss is the in-flight pop.
- **Session path (Phase 4):** Redis down → re-issue session cookie
  (force re-auth). Defer to Phase 4.

**Doctrine check:** financial workflows must surface failure, not hang.
Timeouts >1.5s on hot path means upstream LBs will time out before us;
better to fail fast with a logged reason than to hang.

---

## Authentication and Transport Security

**Phase 2 (dev + early production):**

- `requirepass` only. No ACLs.
- Plain TCP. No TLS.
- Bind to localhost OR Docker bridge network only.

**Phase 4 (production hardening):**

- Redis 6+ ACLs with three roles:
  - `app` — limited command set, no `FLUSHDB`, no `CONFIG`, no `SHUTDOWN`
  - `queue-worker` — same as `app`
  - `admin` (not used by app code, only ops)
- TLS (`rediss://` URL scheme) for any hosted or non-localhost deployment
- `protected-mode yes` always on

**Phase 2 ships the ACL structure in `config/database.php` (commented)
and a `.env.example` block, so the Phase 4 hardening is config-only,
no code change.**

---

## Laravel Facade Surfaces

What gets wired in Phase 2 vs deferred:

| Surface | Driver | Phase 2 | Why |
|---------|--------|---------|-----|
| `Cache::*` | redis (db 1) | ✓ wire | Free perf, Laravel default |
| `Queue::*` | redis (db 2) | ✓ wire | Phase 1 services dispatch jobs |
| `RateLimiter::*` | (via Cache::) | ✓ automatic | Inherits cache backend |
| `Session::*` | file | ✗ defer to Phase 4 | Single-server, no need |
| `APP_MAINTENANCE_DRIVER` | file | ✗ defer | Single-server, no need |
| Horizon | — | ✗ defer to Phase 4 | Dashboard adds ops surface |

**Doctrine rationale:** wire the surfaces the system needs to operate
correctly at scale. Defer surfaces whose benefit only materializes with
multi-server deployment or admin dashboards.

---

## Domain Use Cases

The Redis integration earns its keep at four specific call sites. Each
follows the doctrine: Redis is the *fast-path*, Postgres is the
*authoritative store*.

### 6.1 Idempotency Fast-Path (donations)

```
donor POST /donate → key: idem:donation:{key}
  EXISTS → return cached response, no DB hit
  not exists → INSERT donation, write idem row + SETEX 86400 idem:donation:{key} value
```

- TTL: 24h (matches DB row retention)
- Failure mode: Redis miss on existing row → check DB → rehydrate cache
- Failure mode: Redis SETEX fails after DB INSERT → log warning, donation
  is still correct (DB is authoritative)
- This is the only Redis write where Postgres-write-failure is the
  *acceptable* direction (the inverse would corrupt financial data)

### 6.2 Webhook Dedupe

```
POST /webhooks/{provider} → key: idem:webhook:{provider}:{event_id}
  SET NX EX 604800 1
    acquired → process webhook (INSERT webhook_events, UNIQUE constraint
               is the authoritative gate, then process payment)
    not acquired → return 200 OK immediately (already processed)
```

- TTL: 7 days (matches webhook retention window)
- Failure mode: Redis SET NX fails → INSERT into webhook_events anyway;
  the UNIQUE constraint catches the duplicate. Slower but correct.
- Doctrine check: financial integrity survives Redis outage.

### 6.3 Donation State Locks

- **NOT in Redis.** Lives in Postgres via `SELECT ... FOR UPDATE`
  inside the transaction. Doctrine: financial state transitions belong
  in the database. Redis is too cheap a guarantee for this.

### 6.4 Receipt PDF Cache

- **NOT in Redis.** PDFs live in Laravel Storage (filesystem or S3).
  Path + etag are recorded in Postgres. Redis is the wrong tool for
  large blobs.

---

## Observability

### 7.1 Health Check — `php artisan temple:runtime`

Extends the Phase 2 command from the Laravel runtime spec:

```
$ php artisan temple:runtime
  APP         Temple Trust  (Phase 2)
  PHP         8.3.x
  Database    pgsql   [OK]    postgres@ep-xxx.neon.tech
  Redis       phpredis [OK]  redis://...:6379 db=0
  Cache       redis   [OK]
  Queue       redis   [OK]
  Sessions    file
```

Failure modes print `[DEGRADED]` (cache miss + still serving) or
`[DOWN]` (Redis unreachable) with the actual error string truncated
to avoid leaking credentials.

### 7.2 Scheduled INFO Dump

`app/Console/Kernel.php` schedules `temple:redis:info` every 60 seconds.
The command emits one log line per Redis DB containing:

- memory used / maxmemory
- connected clients
- ops/sec (computed)
- keyspace (keys per DB)
- expired keys (last minute)

No secrets, no command payloads. Stored in `storage/logs/redis-info.log`
with 7-day retention. Cheap enough to always run.

### 7.3 Failure Modes in Logs

Every Redis operation that falls through to Postgres writes a
`WARN` log line with:

- subsystem (cache / queue / idempotency / webhook)
- operation attempted
- error class + truncated message
- fallback path taken

This makes a Redis outage visible without grep-diving app code.

---

## Deployment

### 8.1 composer.json

```json
"require": {
    "php": "^8.2",
    "ext-pdo": "*",
    "ext-redis": "*",
    "laravel/framework": "^10.0",
    ...
}
```

### 8.2 Dockerfile (or docker-compose base)

```dockerfile
FROM php:8.3-cli
RUN pecl install redis \
    && docker-php-ext-enable redis
```

Alpine-based images need `apk add --no-cache $PHPIZE_DEPS` and a build
step. Debian-based images are simpler. Recommend Debian-slim for this
project.

### 8.3 CI (GitHub Actions)

```yaml
- uses: shivammathur/setup-php@v2
  with:
    php-version: '8.3'
    extensions: redis
```

### 8.4 docker-compose.yml (dev)

```yaml
services:
  redis:
    image: redis:7-alpine
    command: ["redis-server", "--requirepass", "${REDIS_PASSWORD:-dev}", "--appendonly", "yes"]
    ports:
      - "6379:6379"
    volumes:
      - redis-data:/data

volumes:
  redis-data:
```

`--appendonly yes` for durability across restarts; without it, queue
jobs in-flight are lost on Redis crash. Acceptable trade-off for dev
only — production uses hosted Redis with AOF + RDB both on.

---

## Configuration (final shape)

### config/database.php — additions

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix'  => env('REDIS_PREFIX', 'temple_trust_'),
    ],
    'default' => [
        'url'      => env('REDIS_URL'),
        'host'     => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port'     => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
        'timeout'  => (float) env('REDIS_TIMEOUT', 1.5),
        'read_timeout' => (float) env('REDIS_READ_TIMEOUT', 0.5),
    ],
    'cache' => [
        // same shape, database => env('REDIS_CACHE_DB', '1')
    ],
    'queue' => [
        // same shape, database => env('REDIS_QUEUE_DB', '2')
    ],
    'session' => [
        // same shape, database => env('REDIS_SESSION_DB', '3'),
        // Phase 4 — left here for config parity, not wired in Phase 2
    ],
],
```

### config/queue.php — change

```php
'default' => env('QUEUE_CONNECTION', 'redis'),  // was 'sync'
```

Test env (`phpunit.xml`) keeps `QUEUE_CONNECTION=sync` so tests don't
need a running Redis.

### config/cache.php — change

```php
'default' => env('CACHE_STORE', 'redis'),  // was 'file'
```

Test env keeps `CACHE_STORE=array`.

### .env.example — additions

```bash
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2
REDIS_SESSION_DB=3
REDIS_TIMEOUT=1.5
REDIS_READ_TIMEOUT=0.5

CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

---

## Testing Strategy

Three test layers, mirroring the Phase 0.5 probe-style validation:

1. **Unit** — fake/in-memory Redis interactions (the contract tests
   already in `tests/Unit/Payments/` provide the template).
2. **Feature** — boot the full app with `REDIS_CLIENT=phpredis` and
   a real Redis instance via Docker. Assert Cache::, Queue::, RateLimiter::
   behave correctly.
3. **Probe-style validation report** — JSON file at repo root mirroring
   `phase-0.5-validation-report.json`. Probes include:
   - redis-ping (PING returns PONG)
   - cache-set-get (Cache::put + Cache::get round-trips)
   - cache-ttl (Cache::put with TTL expires correctly)
   - queue-dispatch (dispatch a job, verify it lands on Redis)
   - queue-pop (worker can pop the job)
   - idem-fastpath-hit (idem key exists → no DB hit)
   - idem-fastpath-miss (idem key absent → DB hit + cache fill)
   - webhook-dedupe-hit (replay same webhook → 200 OK, single DB row)
   - webhook-dedupe-miss (new webhook → DB insert + cache fill)
   - phpredis-extension-loaded (`php -m | grep redis`)
   - connect-timeout-respected (unreachable host → fails within 1.5s)
   - read-timeout-respected (slow command → fails within read_timeout)
   - fail-open-cache (Redis down → file cache serves)
   - fail-closed-payment-path (Redis down → still hits Postgres)

---

## Exit Criteria

Phase 2 Redis integration closes when:

[ ] `composer.json` declares `ext-redis: *`
[ ] Dockerfile / CI installs `ext-redis` cleanly
[ ] `php artisan temple:runtime` prints green for DB, Redis, Cache, Queue
[ ] `config/database.php` declares four Redis DBs (default/cache/queue/session)
[ ] `CACHE_STORE=redis` is the default outside testing
[ ] `QUEUE_CONNECTION=redis` is the default outside testing
[ ] `Session` driver remains `file` (deferred to Phase 4)
[ ] Idempotency fast-path implemented for donations
[ ] Webhook dedupe implemented with Redis SET NX
[ ] Donation state locks remain in Postgres (NOT Redis) — verified by
      grep showing no `lock:donation` SETEX
[ ] Receipt PDFs remain in Laravel Storage (NOT Redis) — verified by
      grep showing no receipt blob cache keys
[ ] Probe-style validation report on disk with all probes passing
[ ] pint + phpstan clean
[ ] No payment path depends on Redis for correctness — verified by
      probing Redis-down behavior in `tests/Feature/Payments/`
[ ] `module-inventory.md` updated with Redis section
[ ] `architecture.md` "Runtime Wiring" section updated

---

## Open Decisions

These are the items I cannot decide from doctrine alone. Each is a
human-judgement call.

**D1 — Hosted vs self-hosted Redis for inter-agent linkage:**

  (a) Hosted (Upstash/Redis Cloud/Aiven) — costs money, no ops burden,
      TLS + ACLs built in, clean inter-agent reach.
  (b) Self-hosted with public firewall — cheaper, ops burden, manual TLS.

  Recommend (a) given the "linkage with other agents' infra" goal. The
  cost of managed Redis for a single-temple workload is in the
  single-digit USD/month range.

**D2 — Redis version target:**

  (a) Redis 7.2 (LTS-ish, RESP3 default-on, widely deployed)
  (b) Redis 7.4 (newer, vector sets)
  (c) Valkey (Redis fork, drop-in, some hosting providers prefer it)

  Recommend (a) for Phase 2. (c) is viable — phpredis speaks Valkey
  unchanged — but adds a vendor-choice dependency for negligible gain.

**D3 — Cache TTL strategy:**

  (a) Single global default (e.g. 3600s) with per-call override
  (b) Per-domain TTL (cache:donation, cache:webhook, etc.)
  (c) No default TTL, every Cache::put must specify

  Recommend (a). Doctrine: avoid premature abstraction. (b) and (c)
  add ceremony without current justification.

**D4 — Failure-mode logging:**

  When Redis is down and we fall through, do we want:
  (a) Single WARN line per fallback (low volume, easy to alert on)
  (b) Metric counter (requires metrics infra — Phase 4 territory)
  (c) Both

  Recommend (a) for Phase 2. Add (c) in Phase 4 when metrics land.

---

## Sequence / Priority

| Step | What | Depends on |
|------|------|------------|
| 1 | `composer.json` + Dockerfile: declare `ext-redis`, install extension | nothing |
| 2 | docker-compose.yml: Redis service with auth + AOF | step 1 |
| 3 | `config/database.php`: four Redis DBs, timeouts, prefixes | step 1 |
| 4 | `.env.example`: full Redis block | step 3 |
| 5 | `config/cache.php` + `config/queue.php`: defaults to redis | step 3 |
| 6 | `phpunit.xml`: keep testing env on array/sync | step 5 |
| 7 | `app/Console/Commands/TempleRuntime.php` + Redis check | step 3 |
| 8 | `app/Console/Commands/TempleRedisInfo.php` + schedule | step 3 |
| 9 | Idempotency fast-path in donation service | Phase 1 closure |
| 10 | Webhook dedupe in webhook controller | Phase 1 closure |
| 11 | Tests: unit + feature + probe report | steps 7-10 |
| 12 | `module-inventory.md` + `architecture.md` updates | steps 1-11 |

Steps 1-8 close the *runtime* integration. Steps 9-10 close the
*domain* integration and depend on Phase 1 closure committing its
uncommitted work. Steps 11-12 close the *validation* integration.

---

## Doctrine Compliance Self-Check

| Doctrine (AGENTS.md / architecture.md) | Compliance |
|----------------------------------------|------------|
| Financial integrity takes precedence | ✓ — Postgres is authoritative; Redis is fast-path only |
| Security takes precedence over speed | ✓ — ACLs + TLS staged; passwords from day 1 |
| Maintainability takes precedence | ✓ — explicit key prefixes; one Redis instance, four DBs |
| Framework conventions over abstractions | ✓ — uses Laravel's Cache/Queue facades unchanged |
| Explicit workflows over implicit | ✓ — failure-mode logs, INFO dump, health check |
| Readable code over compact | ✓ — four config blocks named, not minified |
| Business logic in services | ✓ — Redis calls happen inside services, not controllers |
| Payment verification before persistence | ✓ — webhook dedupe never blocks UNIQUE constraint |
| Secrets in env config only | ✓ — no Redis password in code or .env.example values |
| Avoid unnecessary dependencies | ✓ — Redis is doctrine-required (Phase 2 deliverable) |
| Avoid premature optimization | ✓ — no igbinary, no persistent, no cluster |

---

## Status

SPEC DRAFT — awaiting review.

No composer changes. No code changes. No commits. No branches created.
Awaiting decisions on D1-D4 and an explicit `go` / `proceed` signal to
begin Step 1 of the Sequence table.
