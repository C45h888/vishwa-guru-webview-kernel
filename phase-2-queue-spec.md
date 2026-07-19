# Phase 2 — Queue Configuration Spec

## Purpose

Establish Laravel's queue subsystem as a first-class runtime dependency of
the Temple Trust Management System. This spec defines the durable waiting
room (Redis DB 2), the worker runtime (queue:work in its own container),
the failure-capture surface (failed_jobs + job_batches tables), and the
service-side dispatch contract that all Phase 1 closure jobs will use.

Companion to `phase-2-redis-spec.md`. The Redis spec defined the
substrate; this spec defines how that substrate is consumed by async
workloads.

Doctrine reference: AGENTS.md "Payment Directives" (financial integrity
takes precedence), `architecture.md` "Service Communication" (services
coordinate, controllers do not), `roadmap.md` Phase 2 "Queue
configuration".

---

## Phase 2 status before this work

| Phase 2 deliverable | Status |
|---|---|
| Laravel runtime | DONE — Runtime module (other agent) |
| Neon PostgreSQL integration | DONE — Neon module + temple:neon:ping + README (other agent); one gap: -pooler hostname for serverless connection pooling |
| Redis integration | DONE — `phase-2-redis-spec.md` (Redis module) |
| **Queue configuration** | THIS SPEC |
| Service registration | DONE — bootstrap/providers.php has 6 providers |
| Dependency injection | DONE — Phase 1 closure + module providers |
| Environment configuration | DONE — .env.example + EnvValidator |

This spec closes the last Phase 2 deliverable except the Neon pooler gap,
which is a one-line config addition tracked separately.

---

## Architecture decision: durable waiting room

**Decision:** Redis on logical DB 2 (the `redis.queue` connection I
configured in the Redis turn). PostgreSQL holds the `failed_jobs` and
`job_batches` tables (canonical Laravel shapes).

**Why Redis and not the database queue driver:**
- Doctrine: financial correctness depends on Postgres staying available
  for the donation write path. Putting the queue in Postgres would
  couple two failure domains — when Postgres is under load, the queue
  stalls, which stalls receipt generation, which stalls donor
  confirmations.
- Redis is sub-millisecond for the BRPOPLPUSH-style operations Laravel
  uses. Postgres LISTEN/NOTIFY polling is slower and noisier.
- AOF is on (docker-compose sets `--appendonly yes`). If Redis crashes
  and loses 1 second of jobs, the worst case is a missed receipt
  notification — the donation itself is already persisted in Postgres.

**Why the failed_jobs table lives in Postgres, not Redis:**
- Failed jobs are forensic records. They outlive Redis retention.
- Doctrine: "Financial operations should always be traceable."
- The failed_jobs table is append-only by Laravel convention.

---

## Connection topology

**Decision:** `QUEUE_CONNECTION=redis` is the production default.
The `phpunit.xml` override is `sync` for testing isolation (tests
don't need a Redis container to boot).

**Why the testing env uses sync:**
- Doctrine: tests must be hermetic. A test that depends on a running
  Redis is not hermetic.
- The sync driver runs jobs inline in the dispatching request. Tests
  observe the job's effect through normal assertions, not through
  queue inspection.
- The cost: tests cannot exercise async retry semantics. We compensate
  with explicit retry tests that dispatch jobs designed to fail and
  assert the failure reaches `failed_jobs` via the QueueRetryTest.

---

## Job retry semantics

**Decision (general):** `tries=3`, `backoff=[10, 60, 300]` (10s, 1m, 5m).

**Decision (financial jobs):** `tries=1`, no auto-retry. Fail-fast.
Surfaced immediately to the Failure State Manager.

**Rationale:**
- General-purpose jobs (notification dispatch, audit archival) benefit
  from automatic retry on transient failures (Redis blip, SMTP
  timeout). Three tries with exponential backoff handles 99% of
  transient failures without operator intervention.
- Financial jobs (receipt PDF generation tied to a verified payment,
  donation state transition) MUST NOT auto-retry. The reason: a
  payment has already been verified by the gateway. If the receipt
  job fails twice, retrying it silently is a worse failure than
  surfacing it to ops. Doctrine: financial correctness over
  convenience.

**Configuration shape (env-driven):**

```bash
# General-purpose jobs (default)
QUEUE_RETRY_TRIES_DEFAULT=3
QUEUE_RETRY_BACKOFF_DEFAULT=10,60,300

# Financial jobs (override in job class)
QUEUE_RETRY_TRIES_FINANCIAL=1
QUEUE_RETRY_BACKOFF_FINANCIAL=0
```

The defaults apply via `config/queue.php`. Financial jobs override in
their class definition (`public int $tries = 1;`).

---

## Failed job retention

**Decision:** Auto-prune failed jobs after 30 days via scheduled
`queue:prune-failed`. Forever retention is a forensic option but a
storage liability for a small temple trust.

**Doctrine check:**
- "Financial operations should always be traceable" — yes, but 30
  days is enough for the audit cycle. Beyond 30 days, jobs that
  nobody has acted on are noise.
- The schedule: `php artisan queue:prune-failed --hours=720` runs
  weekly via `app/Console/Kernel.php`.

---

## Laravel facade surface

What gets wired in Phase 2 vs deferred:

| Surface | Wired? | Notes |
|---|---|---|
| `Queue::push()`, `Queue::connection()` | ✓ | Used by services via `QueueConnectorContract` |
| `Bus::batch()` | ✓ | `job_batches` table created |
| `Bus::chain()` | ✓ | Same backing table |
| `php artisan queue:work` | ✓ | Docker worker service runs it |
| `php artisan queue:listen` | ✗ | Dev-only; not in production runtime |
| `php artisan queue:retry` | ✓ | Manual retry surface for ops |
| `php artisan queue:flush` | ✓ | Reserved for ops emergency |
| Horizon | ✗ | Defer to Phase 4 (admin dashboard) |

---

## Domain use cases

The queue substrate earns its keep at four call sites. Each follows the
doctrine: Redis is the *fast-path waiting room*, Postgres is the
*authoritative record*.

**Note:** None of these are implemented in Phase 2. They land in Phase 1
closure. Phase 2 only establishes the substrate.

### Use case 6.1 — Receipt PDF generation

```
Payment verified → ReceiptPdfGenerationJob dispatch → 
  worker pops → generates PDF → writes to Laravel Storage →
  updates receipts row → dispatches SendReceiptEmailJob →
  on success: marks receipt delivered; on failure: lands in failed_jobs
```

- Job class: `App\Jobs\ReceiptPdfGenerationJob` (Phase 1 territory)
- Queue: `receipts` (named queue for prioritization)
- Tries: 1 (financial — fail-fast)
- Backoff: 0
- Payload: `ReceiptId` value object (typed, not raw array)

### Use case 6.2 — Receipt email delivery

```
Receipt PDF ready → SendReceiptEmailJob dispatch →
  worker pops → SMTP send → on success: marks receipt delivered →
  on failure: lands in failed_jobs after 3 tries
```

- Job class: `App\Jobs\SendReceiptEmailJob` (Phase 1 territory)
- Queue: `notifications`
- Tries: 3 (transient SMTP failures should retry)
- Backoff: [10, 60, 300]

### Use case 6.3 — Webhook retry on transient failure

```
Webhook callback → signature verify → dispatch payment →
  on transient failure (5xx from gateway, timeout) →
  WebhookRetryJob dispatch → worker pops →
  re-attempts payment dispatch with backoff
```

- Job class: `App\Jobs\WebhookRetryJob` (Phase 1 territory)
- Queue: `webhooks`
- Tries: 5 (long retry — gateways have multi-second blips)
- Backoff: [30, 120, 600, 1800, 3600] (30s, 2m, 10m, 30m, 1h)

### Use case 6.4 — Audit event archival

```
Audit event recorded → AuditEventArchiveJob dispatch →
  worker pops → flushes audit buffer to long-term store
```

- Job class: `App\Jobs\AuditEventArchiveJob` (Phase 1 territory)
- Queue: `audit` (lowest priority)
- Tries: 3
- Backoff: [60, 300, 900]

### What is NOT queued

These belong to the synchronous request cycle. The queue does NOT replace
them:

| Operation | Why synchronous |
|---|---|
| Payment signature verification | Doctrine: NEVER trust client response without gateway verification, and verification blocks the response |
| Idempotency check | Cache hit returns cached response; miss must check DB before responding |
| Webhook 200 acknowledgement | Gateway needs the response within their timeout |
| Input validation | Bad input → 422 immediately |
| Donation state transition lock | `SELECT FOR UPDATE` in transaction |

---

## Observability

### 7.1 — `php artisan temple:queue:stats`

Custom command (new in Phase 2). Prints:

```
$ php artisan temple:queue:stats
  Connection     redis (DB 2)
  Queues         default: 0  receipts: 0  notifications: 0  webhooks: 0  audit: 0
  Failed (24h)   0
  Oldest pending 0s
  Workers        1 active (pid 12345)
  Health         OK
```

Doctrine: every operational artifact must have a corresponding surface
for ops to read without spelunking through code.

### 7.2 — `temple:runtime` integration

The `QueueHealthProbe` (in Runtime module) is already wired. It uses
`Queue::connection()->size(null)` to report depth. This spec adds the
`temple:queue:stats` command as the *ops* surface; the probe is the
*health* surface.

### 7.3 — Failed jobs surfaced via temple:runtime

When a probe detects queue depth > threshold (configurable, default
100), the runtime's ProbeFailureHandler can be extended to surface
this. Out of scope for Phase 2; tracked for Phase 4 ops surface.

---

## Deployment

### 8.1 — Docker Compose worker service

```yaml
services:
  app:
    # ... existing app service
    depends_on:
      - redis
      - postgres
      - worker

  worker:
    build: .
    command: php artisan queue:work redis --tries=3 --backoff=10,60,300 --max-time=3600 --sleep=3
    environment:
      QUEUE_CONNECTION: redis
      REDIS_HOST: redis
      # ... same as app
    depends_on:
      - redis
      - postgres
```

Why a separate container:
- Doctrine: HTTP serving and async processing are different concerns.
  A stuck worker (infinite loop, deadlock) must not take down the
  donation form.
- Workers are scaled independently from web containers. If queue
  depth spikes, you scale workers without touching web tier.

### 8.2 — Production worker tuning (env)

```bash
QUEUE_WORKER_TRIES=3
QUEUE_WORKER_BACKOFF=10,60,300
QUEUE_WORKER_MAX_TIME=3600       # restart worker hourly (memory leak guard)
QUEUE_WORKER_SLEEP=3             # seconds between empty queue polls
QUEUE_WORKER_TIMEOUT=60          # per-job hard timeout
```

### 8.3 — supervisord fallback (non-Docker deploys)

For environments that don't use Docker Compose, a supervisord config
runs the worker alongside `php artisan serve`:

```ini
[program:temple-trust-worker]
command=php /var/www/html/artisan queue:work redis --tries=3 --backoff=10,60,300 --max-time=3600
user=www-data
autostart=true
autorestart=true
numprocs=2
process_name=%(program_name)s_%(process_num)02d
stdout_logfile=/var/log/temple-trust/worker.log
```

---

## Configuration (final shape)

### `config/queue.php` — additions

The existing config from Phase 0.25 already declares sync/database/redis
connections and failed/batching sections. Phase 2 adds:

```php
// Default retry tuning (per-queue or per-job overrides take precedence)
'general' => [
    'tries'   => (int) env('QUEUE_RETRY_TRIES_DEFAULT', 3),
    'backoff' => env('QUEUE_RETRY_BACKOFF_DEFAULT', '10,60,300'),
    'max_time'    => (int) env('QUEUE_WORKER_MAX_TIME', 3600),
    'sleep'       => (int) env('QUEUE_WORKER_SLEEP', 3),
    'timeout'     => (int) env('QUEUE_WORKER_TIMEOUT', 60),
],
'financial' => [
    'tries'   => (int) env('QUEUE_RETRY_TRIES_FINANCIAL', 1),
    'backoff' => env('QUEUE_RETRY_BACKOFF_FINANCIAL', '0'),
],
'prune' => [
    'failed_after_hours' => (int) env('QUEUE_FAILED_RETENTION_HOURS', 720),
],
```

### `config/database.php` — unchanged

The `redis.queue` connection (DB 2) was wired in the Redis turn. No
changes needed here.

---

## Testing strategy

Three test layers:

1. **Unit** — `QueueConnectorContract` test doubles, no Laravel boot
2. **Feature** — boot the app, dispatch jobs, observe outcomes
3. **Probe-style** — `scripts/phase-2-queue-probes.php` (new) mirroring
   `phase-2-redis-probes.php`

**Probe set (q01-q08):**
- q01: `QueueConnectorContract` resolves
- q02: redis.queue connection configured (DB 2)
- q03: dispatch a job lands on Redis
- q04: worker pops and runs the job
- q05: failed_jobs table exists (postgres) OR fallback to database driver
- q06: job throws → retry with backoff → exhaust → lands on failed_jobs
- q07: financial job with tries=1 → single throw → immediate fail
- q08: queue:prune-failed respects retention hours

---

## Exit criteria

Phase 2 Queue configuration closes when:

[ ] `database/migrations/2026_07_16_000003_create_queue_tables_postgres.php` committed
[ ] `database/migrations/2026_07_16_000004_create_queue_tables_sqlite.php` committed
[ ] `app/Jobs/` directory exists with `AbstractQueuedJob` base class
[ ] `app/Jobs/Templates/HealthCheckQueuedJob.php` example committed
[ ] `app/Queue/Contracts/QueueConnectorContract.php` committed
[ ] `app/Queue/Infrastructure/LaravelQueueConnector.php` committed
[ ] `app/Queue/Console/Commands/QueueStatsCommand.php` committed
[ ] `app/Queue/Providers/QueueServiceProvider.php` registered in `bootstrap/providers.php`
[ ] `docker-compose.yml` has `worker` service
[ ] `config/queue.php` has `general`, `financial`, `prune` blocks
[ ] `.env.example` has queue worker tuning knobs
[ ] `tests/Feature/Queue/QueueBindingsTest.php` passes
[ ] `tests/Feature/Queue/QueueDispatchTest.php` passes
[ ] `tests/Feature/Queue/QueueRetryTest.php` passes
[ ] `tests/Feature/Queue/PaymentPathSurvivesQueueDownTest.php` passes
[ ] `scripts/phase-2-queue-probes.php` exits 0 against live Redis
[ ] `module-inventory.md` updated with Queue section
[ ] `architecture.md` updated with Queue Configuration section
[ ] pint + phpstan clean
[ ] No `Queue::` facade reference in app code outside Queue module
    (verified by grep; service code uses `QueueConnectorContract` only)

---

## Open decisions

**D1 — Worker deployment shape:**
  (a) Docker Compose `worker` service (recommended for local + container prod)
  (b) supervisord inside app container (alternative for non-Docker deploys)
  (c) systemd unit (production only)

  Recommend (a) for dev + (c) for production. (b) couples concerns.

**D2 — Job retry semantics:**
  (a) tries=3, backoff=[10,60,300] (general); tries=1, backoff=0 (financial)
  (b) tries=5, backoff=exponential across the board
  (c) tries=1, fail-fast across the board

  Recommend (a). Doctrine: financial jobs surface failures immediately.

**D3 — Failed job retention:**
  (a) 30 days, auto-prune weekly (recommended)
  (b) Forever (manual cleanup only)
  (c) 7 days (aggressive)

  Recommend (a). 30 days covers audit cycles; beyond that, noise.

**D4 — QueueConnectorContract scope:**
  (a) Thin wrapper over Queue:: (mirrors RedisConnectorContract)
  (b) Skip the contract; use Queue:: facade directly in service code
  (c) Per-domain queue contracts (PaymentQueueContract, etc.)

  Recommend (a). Same doctrine: facade forbidden in domain code.

**D5 — Multiple workers per queue vs one big worker:**
  (a) One `queue:work` process per container, multiple containers (recommended)
  (b) supervisord `numprocs=N` inside one container
  (c) Horizon's auto-scaling

  Recommend (a). Doctrine: explicit, observable, restartable per container.

**D6 — Job payload shape:**
  (a) Typed value objects (Identifier, Money, ReceiptId) in constructor
  (b) Raw scalars (int $id, string $kind)
  (c) Eloquent models passed directly (anti-pattern)

  Recommend (a). Doctrine: typed payloads prevent silent schema drift.

---

## Sequence (priority)

| Step | What | Depends on |
|------|------|------------|
| 1 | `database/migrations/2026_07_16_000003_create_queue_tables_postgres.php` | nothing |
| 2 | `database/migrations/2026_07_16_000004_create_queue_tables_sqlite.php` | step 1 |
| 3 | `app/Jobs/AbstractQueuedJob.php` (base class with typed payload doctrine) | nothing |
| 4 | `app/Jobs/Templates/HealthCheckQueuedJob.php` (example) | step 3 |
| 5 | `app/Queue/Contracts/QueueConnectorContract.php` | nothing |
| 6 | `app/Queue/Infrastructure/LaravelQueueConnector.php` | step 5 |
| 7 | `app/Queue/Console/Commands/QueueStatsCommand.php` | step 6 |
| 8 | `app/Queue/Providers/QueueServiceProvider.php` + `bootstrap/providers.php` | step 6 |
| 9 | `config/queue.php` retry/prune blocks + `.env.example` worker tuning | step 1 |
| 10 | `docker-compose.yml` worker service | step 1 |
| 11 | `tests/Feature/Queue/QueueBindingsTest.php` | steps 5-8 |
| 12 | `tests/Feature/Queue/QueueDispatchTest.php` | step 11 |
| 13 | `tests/Feature/Queue/QueueRetryTest.php` | step 12 |
| 14 | `tests/Feature/Queue/PaymentPathSurvivesQueueDownTest.php` | step 12 |
| 15 | `scripts/phase-2-queue-probes.php` | step 11 |
| 16 | `module-inventory.md` + `architecture.md` updates | steps 1-15 |

Steps 1-10 close the **substrate**. Steps 11-14 close the **validation**.
Step 16 closes the **documentation**.

---

## Doctrine Compliance Self-Check

| Doctrine | Compliance |
|---|---|
| Financial integrity takes precedence | ✓ — financial jobs fail-fast (tries=1) |
| Security takes precedence over speed | ✓ — worker has per-job hard timeout |
| Maintainability takes precedence | ✓ — QueueConnectorContract abstraction, named queues |
| Framework conventions over abstractions | ✓ — uses Laravel Queue facade unchanged |
| Explicit workflows over implicit | ✓ — named queues, typed payloads, retry knobs explicit |
| Readable code over compact | ✓ — retry config visible in env, not buried |
| Business logic in services | ✓ — service code dispatches; workers run inside service layer |
| Payment verification before persistence | ✓ — webhook dispatch is post-verification |
| Secrets in env config only | ✓ — no Redis or Postgres secrets in code |
| Avoid unnecessary dependencies | ✓ — Horizon, AWS SQS, Beanstalk explicitly deferred |
| Avoid premature optimization | ✓ — single worker container; scale when needed |

---

## Status

SPEC DRAFT — awaiting review.

No composer changes. No code changes. No commits. Awaiting:
  1. Decisions on D1-D6 (defaults shown as recommendations)
  2. `go` / `proceed` to execute Step 1 of the Sequence table

The Neon integration the other agent landed is substantially complete.
The one remaining gap is `-pooler` hostname support for serverless
connection pooling — that's a one-line config addition tracked
separately, not in this spec's scope.
