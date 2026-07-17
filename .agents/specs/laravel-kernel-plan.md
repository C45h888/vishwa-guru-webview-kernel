Section 1 — Architecture

**Goal:** lay down the module boundaries, layering, and how the new code fits into the existing kernel.

**Top-level shape**

```
app/
  Shared/                    [Phase 0.25 — unchanged]     cross-cutting contracts, value objects, exceptions
  Persistence/               [Phase 0.25 — adds provider]  kernel-level data access abstractions
    Contracts/
    Infrastructure/          LaravelDbAdapter exists; provider is the missing wiring
    Providers/               NEW: PersistenceServiceProvider.php
    ValueObjects/
  Runtime/                   NEW MODULE                   runtime infrastructure (health, env, commands)
    Providers/
    Console/Commands/
    Http/Controllers/
    Validation/
    Diagnostics/
  Payments/                  [Phase 1 — out of scope]
  ...
```

**Layering and direction of dependency**

```
        HTTP request  ──►  Runtime\Http\Controllers
                              │
                              ▼
                          Runtime\Diagnostics\HealthProbe (interface)
                              │ uses
                              ▼
                          Persistence\Contracts\PersistenceAdapterContract
                              │ bound to
                              ▼
                          Persistence\Infrastructure\LaravelDbAdapter
                              │ uses
                              ▼
                          Illuminate\Database\ConnectionInterface

        Boot sequence  ──►  SharedServiceProvider
                              │ (ConfigurationContract, EnvironmentContract, Clock,
                              │  IdentifierGenerator, ConfigurationRegistry)
                              ▼
                          PersistenceServiceProvider
                              │ (PersistenceAdapterContract → LaravelDbAdapter,
                              │  RepositoryRegistryContract)
                              ▼
                          RuntimeServiceProvider
                              │ (EnvValidator, BootProbe, HealthProbes, Commands,
                              │  Routes via RouteServiceProvider, HealthController,
                              │  PingController)
                              ▼
                          Application boots → Runtime\Validation\BootProbe::assert()
                              │ (fail-fast on missing required env)
                              ▼
                          Ready to serve
```

**Key architectural rules this design enforces**

1. **Runtime depends on Persistence contracts, never on Eloquent or DB:: facade.** `HealthProbe` implementations receive `PersistenceAdapterContract` and use its `query()` method for a `SELECT 1` — no DB coupling.
2. **RuntimeServiceProvider is the only place that touches Laravel's `Router`, `Artisan`, and `View` facades** for runtime concerns. Provider-specific bindings (Payment, Notification, etc.) register their own providers.
3. **AppServiceProvider stays empty** — it exists as a future home for app-wide bindings but contributes nothing in this spec. This keeps the kernel composer-readable: every Provider in `bootstrap/providers.php` has a single, named purpose.
4. **RouteServiceProvider gains one responsibility**: load `routes/runtime.php` (a NEW file) under the `api/v1` prefix. It still loads `routes/web.php` and `routes/console.php` as before. Web routes (Phase 3 public site) and runtime routes (Phase 2) are physically separated.
5. **EnvValidator is the only thing that reads `env()` for runtime concern keys.** No service in any other module reads `env()` directly — Phase 0.25 doctrine says "ConfigurationContract reads from config(), not env()". EnvValidator exists because the *bootstrap* must know what's required to even load config; this is a meta-concern, not a service concern.

**What this design deliberately does NOT do (out-of-scope guards)**

- Does not register `PaymentsServiceProvider` — that's a Phase 1 commit, separate concern.
- Does not touch Neon specifics — that's the next spec.
- Does not introduce a `Runtime` interface — concrete classes only; abstractions only where multiple implementations already exist (HealthProbe, since there will be DB, Cache, Queue probes).
- Does not add queue workers, scheduled tasks, or request lifecycle middleware registration — those are roadmap M5 items for a follow-on Phase 2b spec. The Runtime module is their future home.
Section 2 — Components

For each class: purpose, public surface, dependencies. ~20 new files. Let me know if any of these need to change shape.

---

### Persistence module additions

**`App\Persistence\Providers\PersistenceServiceProvider`**
- **Purpose:** Wires the kernel-level persistence abstractions. Single responsibility: bind two contracts.
- **Public surface:** `register()`, `provides()`
- **Bindings:**
  - `PersistenceAdapterContract → LaravelDbAdapter` (singleton). Constructor: Laravel resolves `Illuminate\Database\ConnectionInterface` from `DB::connection()` (the default connection) and passes it in. Container does this automatically — no closure needed.
  - `RepositoryRegistryContract → RepositoryRegistryImpl` (singleton, lazy). The Registry is **not** a Phase 0.25 contract binding target yet — let me verify.
- **Dependencies:** `Illuminate\Support\ServiceProvider`. No app dependencies.
- **Location rationale:** `App\Persistence\Providers\` (per Hermes D1). Doctrine: domain-boundary explicit.
- **Open question for you:** is `RepositoryRegistryContract` registered here or in `RuntimeServiceProvider`? My read: **here in Persistence** — it's a persistence concern. Confirm or push back.

---

### Runtime module — Providers

**`App\Runtime\Providers\RuntimeServiceProvider`**
- **Purpose:** Central runtime wiring. Registers everything in the Runtime module.
- **`register()`:** binds `EnvValidator`, `BootProbe`, `KernelSnapshotFactory` (or `KernelSnapshot` directly), 3 `HealthProbe` implementations tagged as `runtime.health_probe`, `HealthCheckAggregator` (resolves the tagged probes), `HealthController`, `PingController`, registers 2 artisan commands.
- **`boot()`:** calls `BootProbe::assert()` exactly once, guarded so it doesn't re-run in deferred-provider scenarios. (BootProbe is idempotent — running it on every request is cheap but pointless.)
- **`provides()`:** returns the list of registered services.
- **Dependencies:** `Illuminate\Support\ServiceProvider`.

---

### Runtime module — Console commands

**`App\Runtime\Console\Commands\RuntimeStatusCommand` — `php artisan temple:runtime`**
- **Purpose:** Print runtime status table to console (driver, cache store, queue driver, app key present, DB ping, app env, php version, Laravel version, persistence adapter identifier).
- **Public surface:** `handle(): int` returning Symfony `Command::SUCCESS`/`FAILURE`.
- **Dependencies:** `HealthCheckAggregator` (from DI), `KernelSnapshot`, `ConfigurationContract`, `EnvironmentContract`.

**`App\Runtime\Console\Commands\EnvironmentListCommand` — `php artisan temple:env`**
- **Purpose:** Print a table of required env keys per environment, showing present/missing for current env. Helps ops debug "what's actually required here?".
- **Public surface:** `handle(): int`
- **Dependencies:** `EnvValidator`, `ConfigurationContract`, `EnvironmentContract`.

---

### Runtime module — HTTP controllers

**`App\Runtime\Http\Controllers\HealthController` — `GET /health`**
- **Purpose:** Returns HTTP 200 with per-subsystem JSON if all probes healthy. Returns HTTP 503 with which subsystems failed otherwise. Used by load balancers / uptime monitors.
- **Public surface:** `__invoke(Request $request): JsonResponse`
- **Response shape:**
  ```json
  {
    "status": "ok" | "degraded",
    "checked_at": "2026-07-16T19:00:00+05:30",
    "subsystems": {
      "database": {"status": "ok", "latency_ms": 2.3},
      "cache": {"status": "ok", "latency_ms": 0.4},
      "queue": {"status": "ok", "latency_ms": 1.1}
    },
    "runtime": {"php": "8.2.x", "laravel": "10.x", "env": "production"}
  }
  ```
- **Dependencies:** `HealthCheckAggregator`, `KernelSnapshot`, `EnvironmentContract`.

**`App\Runtime\Http\Controllers\PingController` — `GET /api/v1/ping`**
- **Purpose:** Minimal liveness probe. Just returns `{"ping": "pong", "v": "1"}`. No subsystem checks — that lives at `/health`. Used by client apps and CI.
- **Public surface:** `__invoke(): JsonResponse`
- **Dependencies:** none (just `response()->json()`).

---

### Runtime module — Validation

**`App\Runtime\Validation\EnvValidator`**
- **Purpose:** Holds the per-environment required-key matrix. Given an `EnvironmentType`, returns the list of required keys + which are missing.
- **Public surface:**
  - `__construct(private readonly ConfigurationContract $config, private readonly EnvironmentContract $env)`
  - `requiredKeys(EnvironmentType $type): array<string, bool>` (key → required)
  - `missingKeys(EnvironmentType $type): array<int, string>` (just the missing ones)
  - `assert(EnvironmentType $type): void` — throws `RuntimeException` listing all missing keys with set-it instructions
- **Dependencies:** `ConfigurationContract`, `EnvironmentContract`. Reads from `env()` only for keys it needs to validate.
- **Required key matrix (initial draft, open for your input):**

  | Key | Local | Testing | Production |
  |---|---|---|---|
  | `APP_KEY` | required | required | required |
  | `APP_URL` | required | required | required |
  | `DB_CONNECTION` | required | required | required |
  | `DB_HOST` *or* `DATABASE_URL` | required | n/a (sqlite) | required |
  | `DATABASE_URL` | optional | n/a | required (Neon) |
  | `CACHE_STORE` | required | required | required |
  | `QUEUE_CONNECTION` | required | required | required |
  | `REDIS_HOST` *or* `REDIS_URL` | optional | n/a | required |
  | `RAZORPAY_KEY_ID` | optional | optional | required |
  | `RAZORPAY_KEY_SECRET` | optional | optional | required |
  | `RAZORPAY_WEBHOOK_SECRET` | optional | optional | required |
  | `PAYPAL_CLIENT_ID` | optional | optional | required |
  | `PAYPAL_CLIENT_SECRET` | optional | optional | required |
  | `NEON_BRANCH` | optional | n/a | required |
  | `NEON_ROLE` | optional | n/a | required |

  (Neon-specific keys are listed here for completeness but the Neon connection preset / SSL fix is the NEXT spec. This validator only checks presence, not semantics.)

**`App\Runtime\Validation\BootProbe`**
- **Purpose:** Single entry point called by `RuntimeServiceProvider::boot()`. Runs the full boot assertion. Idempotent.
- **Public surface:**
  - `__construct(private readonly EnvValidator $envValidator, private readonly EnvironmentContract $env)`
  - `assert(): void` — calls `$envValidator->assert($env->type())`, then runs `KernelSnapshot::capture()` to verify the framework actually loaded. Throws on any failure with a single combined message.
  - `alreadyRan(): bool` — for the boot-once guard.
- **Dependencies:** `EnvValidator`, `EnvironmentContract`. **Calls `$envValidator` only in `assert()` — no eager work in constructor.**

---

### Runtime module — Diagnostics

**`App\Runtime\Diagnostics\KernelSnapshot`** — *value object (immutable)*
- **Fields:** `phpVersion: string`, `laravelVersion: string`, `environment: EnvironmentType`, `dbDriver: string`, `cacheStore: string`, `queueDriver: string`, `appKeyPresent: bool`, `appKeyLength: int`, `capturedAt: \DateTimeImmutable`
- **Public surface:** `capture(ConfigurationContract, EnvironmentContract, ConnectionInterface, CacheRepository $cache, QueueManager $queue): self` (named constructor — gathers facts from container). Pure reads, no side effects.
- **Dependencies:** Laravel facades (one place where facades are OK because this IS runtime infrastructure).

**`App\Runtime\Diagnostics\HealthCheckResult`** — *value object (immutable)*
- **Fields:** `name: string`, `healthy: bool`, `latencyMs: float`, `detail: ?string`
- **Public surface:** `ok(name, latencyMs, detail?)`, `fail(name, latencyMs, detail)` named constructors.

**`App\Runtime\Diagnostics\HealthProbe`** — *interface*
- **Public surface:** `name(): string`, `probe(): HealthCheckResult`
- **Single-method contract for any subsystem probe.**

**`App\Runtime\Diagnostics\DatabaseHealthProbe`**
- **Purpose:** Runs `SELECT 1` via `PersistenceAdapterContract::query()` (no DB:: facade).
- **Public surface:** `name(): "database"`, `probe(): HealthCheckResult`
- **Dependencies:** `PersistenceAdapterContract`. **Times the call** for latency reporting.

**`App\Runtime\Diagnostics\CacheHealthProbe`**
- **Purpose:** Sets + reads a throwaway key via `Cache::store()`.
- **Public surface:** `name(): "cache"`, `probe(): HealthCheckResult`
- **Dependencies:** `Illuminate\Contracts\Cache\Repository`.

**`App\Runtime\Diagnostics\QueueHealthProbe`**
- **Purpose:** Verifies queue connection via `Queue::connection()->size(null)` (no payload push). Sync queue returns 0 immediately; real queues report connection.
- **Public surface:** `name(): "queue"`, `probe(): HealthCheckResult`
- **Dependencies:** `Illuminate\Queue\QueueManager`.

**`App\Runtime\Diagnostics\HealthCheckAggregator`**
- **Purpose:** Resolves all tagged `HealthProbe`s via `$app->tagged('runtime.health_probe')` and runs them; aggregates results.
- **Public surface:** `probe(): array<string, HealthCheckResult>` (name → result), `allHealthy(): bool`
- **Dependencies:** `Container`.

---

### Routes file

**`routes/runtime.php`** (NEW)
- **Purpose:** Runtime HTTP endpoints. Loaded by `RouteServiceProvider::boot()` under `prefix => api/v1`.
- **Contents:**
  - `GET /health` → `HealthController` (NOT under `/api/v1` — registered separately in `routes/web.php` for uptime monitoring convenience)
  - `GET /api/v1/ping` → `PingController`
- **Question:** should `/health` live under `/api/v1/health` or stay top-level? Top-level is more conventional for load balancers (matches `/healthz` patterns). **My recommendation: top-level `/health`.** Confirm or push back.

---

### Provider registration edits

**`bootstrap/providers.php`** (EDIT) — add to the returned array, in order:
```php
return [
    App\Providers\AppServiceProvider::class,
    App\Shared\Providers\SharedServiceProvider::class,
    App\Persistence\Providers\PersistenceServiceProvider::class,  // NEW
    App\Runtime\Providers\RuntimeServiceProvider::class,           // NEW
];
```

**`app/Providers/RouteServiceProvider.php`** (EDIT) — add `routes/runtime.php` registration alongside existing web + console routes. `/api/v1/ping` registered under the api group; `/health` registered as top-level middleware group `web` (so session/cookies don't interfere; minimal middleware).

---

### Tests (7 files, all Feature)

| File | Covers |
|---|---|
| `tests/Feature/Runtime/PersistenceBindingsTest.php` | `PersistenceAdapterContract` resolves to `LaravelDbAdapter`; `RepositoryRegistryContract` resolves; `isConnected()` returns true against sqlite `:memory:` |
| `tests/Feature/Runtime/RuntimeServiceProviderTest.php` | Provider declares `provides()` correctly; all bindings resolve; boot runs `BootProbe::assert()` exactly once |
| `tests/Feature/Runtime/EnvValidatorTest.php` | Per-env required-key matrix correctness; `missingKeys()` returns expected lists; `assert()` throws with combined message listing every missing key |
| `tests/Feature/Runtime/HealthControllerTest.php` | `/health` returns 200 + healthy JSON when all probes OK; returns 503 when any probe fails (mock one probe to fail); JSON shape matches spec |
| `tests/Feature/Runtime/RuntimeStatusCommandTest.php` | `temple:runtime` prints expected table; non-zero exit when DB unreachable |
| `tests/Feature/Runtime/BootProbeTest.php` | `assert()` succeeds in testing env with full env; throws in fake "production" env missing `DATABASE_URL`; idempotent (`alreadyRan()` true after first call) |
| `tests/Feature/Runtime/Diagnostics/HealthProbeTest.php` | Data-provider test against all 3 probes; each returns `HealthCheckResult` with positive latency; `DatabaseHealthProbe` uses `PersistenceAdapterContract` (verify via container binding)
Section 3 — Data Flow

Four flows. Each ends with the contract it produces. All are testable in isolation.

---

### Flow A — Application boot (one-time, at process start)

```
1. PHP entrypoint (public/index.php or artisan)
       │ loads vendor/autoload.php
       ▼
2. bootstrap/app.php
       │ creates Illuminate\Foundation\Application
       │ binds HttpKernel, ConsoleKernel, ExceptionHandler (singletons)
       │ loads bootstrap/providers.php
       ▼
3. Provider registration loop (in order from bootstrap/providers.php)
       │
       │  3a. AppServiceProvider::register()        — no-op placeholder
       │  3b. SharedServiceProvider::register()     — ConfigurationContract,
       │                                            EnvironmentContract,
       │                                            Clock, IdentifierGenerator,
       │                                            ConfigurationRegistry
       │  3c. PersistenceServiceProvider::register()
       │       • PersistenceAdapterContract → LaravelDbAdapter (singleton)
       │         Laravel resolves ConnectionInterface from DB::connection() default
       │       • RepositoryRegistryContract → RepositoryRegistryImpl (singleton)
       │  3d. RuntimeServiceProvider::register()
       │       • EnvValidator (singleton)
       │       • BootProbe (singleton)
       │       • DatabaseHealthProbe, CacheHealthProbe, QueueHealthProbe
       │         → tagged as 'runtime.health_probe'
       │       • HealthCheckAggregator (singleton; resolves tagged probes)
       │       • HealthController, PingController (transient)
       │       • RuntimeStatusCommand, EnvironmentListCommand
       ▼
4. Provider boot() calls (same order)
       │
       │  4a. AppServiceProvider::boot()          — no-op
       │  4b. SharedServiceProvider::boot()       — no-op
       │  4c. PersistenceServiceProvider::boot()  — no-op (registry is
       │                                             populated by Payments
       │                                             provider later, in Phase 1)
       │  4d. RuntimeServiceProvider::boot()
       │       └─► BootProbe::assert()
       │             • EnvValidator::assert(env()->type())
       │                → on missing keys: throws RuntimeException
       │                  with one combined message listing
       │                  every missing key + how to set each
       │             • KernelSnapshot::capture(...)
       │                → throws if framework state is unrecoverable
       │             • marks $alreadyRan = true (static guard)
       ▼
5. Application ready
       HTTP requests OR artisan commands OR queue jobs may now run
```

**Guarantees after boot:**
- `PersistenceAdapterContract` resolves to a `LaravelDbAdapter` already connected to `DB::connection()` default
- `RepositoryRegistryContract` resolves (empty registry until Phase 1 payments provider registers entities)
- `HealthCheckAggregator` resolves with all 3 probes tagged
- All artisan commands registered
- Missing required env keys cause a fail-fast exception at boot, not a 500 at first request

**Why the boot probe runs once and not per request:**
- `$alreadyRan` static guard on `BootProbe` prevents re-validation on every container resolution in long-running PHP-FPM workers. Tested via `BootProbeTest::it_does_not_re_run_after_first_assert()`.

---

### Flow B — HTTP `GET /health` (uptime monitor / load balancer)

```
1. Request lands at public/index.php
       │
       ▼
2. HTTP kernel middleware pipeline (Http/Kernel.php)
       │ global: HandleCors, ValidatePostSize, ConvertEmptyStringsToNull
       │ route group: web (EncryptCookies, AddQueuedCookiesToResponse,
       │             StartSession, ShareErrorsFromSession, VerifyCsrfToken,
       │             SubstituteBindings)
       │ — health endpoint exempted from CSRF via
       │   RouteServiceProvider registering it under 'web' group but
       │   the route itself is GET (CSRF only checks POST/PUT/PATCH/DELETE)
       ▼
3. RouteServiceProvider resolves GET /health → HealthController
       │
       ▼
4. HealthController::__invoke(Request $r)
       │ $aggregator = app(HealthCheckAggregator::class)
       │ $snapshot   = KernelSnapshot::capture(...)
       │
       │ foreach $aggregator->probe() as $name => HealthCheckResult:
       │     subsystem[$name] = ['status' => ok|fail, 'latency_ms' => N, 'detail' => ...]
       │
       │ overall = all healthy ? 'ok' : 'degraded'
       │ status  = all healthy ? 200 : 503
       ▼
5. JsonResponse
       {
         "status": "ok" | "degraded",
         "checked_at": "<ISO-8601>",
         "subsystems": { "database": {...}, "cache": {...}, "queue": {...} },
         "runtime": { "php": "8.2.x", "laravel": "10.x", "env": "production" }
       }
```

**Failure semantics:**
- HTTP 200 + `status: "ok"` when all 3 probes return `healthy: true`
- HTTP 503 + `status: "degraded"` + which subsystems failed (with detail)
- Load balancers should mark instance unhealthy on 503
- DatabaseHealthProbe failure does NOT block the response — it reports in JSON. (Doctrine: health checks must never throw — they observe.)

---

### Flow C — HTTP `GET /api/v1/ping` (client liveness)

```
1. Request lands → middleware pipeline (same as B)
       ▼
2. RouteServiceProvider resolves GET api/v1/ping → PingController
       │ (api middleware group: ThrottleRequests, SubstituteBindings)
       ▼
3. PingController::__invoke()
       │ returns response()->json(['ping' => 'pong', 'v' => '1'])
       ▼
4. JsonResponse 200, body: {"ping":"pong","v":"1"}
```

**Why no subsystem checks:** `/api/v1/ping` is a *liveness* probe (the process is alive). `/health` is a *readiness* probe (subsystems are usable). Kubernetes/Rails/ASP.NET all distinguish these. We follow that pattern.

**Why no DB/cache/queue call:** `/api/v1/ping` must work even when the DB is down. It's the "is the PHP process serving HTTP?" probe.

---

### Flow D — Artisan `php artisan temple:runtime`

```
1. artisan calls ConsoleKernel → finds RuntimeStatusCommand
       │ (registered by RuntimeServiceProvider::register())
       ▼
2. RuntimeStatusCommand::handle()
       │ $aggregator = app(HealthCheckAggregator::class)
       │ $snapshot   = KernelSnapshot::capture(...)
       │ $env        = app(EnvironmentContract::class)
       │ $config     = app(ConfigurationContract::class)
       │
       │ $rows = [
       │   ['php',          $snapshot->phpVersion],
       │   ['laravel',      $snapshot->laravelVersion],
       │   ['env',          $env->type()->value],
       │   ['db driver',    $snapshot->dbDriver],
       │   ['cache store',  $snapshot->cacheStore],
       │   ['queue driver', $snapshot->queueDriver],
       │   ['app key',      $snapshot->appKeyPresent ? 'present' : 'MISSING'],
       │   ['captured',     $snapshot->capturedAt->format(DateTimeImmutable::ATOM)],
       │   ['database',     $aggregator->probe()['database']->healthy ? 'OK' : 'FAIL'],
       │   ['cache',        $aggregator->probe()['cache']->healthy ? 'OK' : 'FAIL'],
       │   ['queue',        $aggregator->probe()['queue']->healthy ? 'OK' : 'FAIL'],
       │ ]
       │
       │ $this->table(['Subsystem', 'Status'], $rows)
       │
       │ return $aggregator->allHealthy() ? self::SUCCESS : self::FAILURE
       ▼
3. Shell exit code 0 if all OK, 1 otherwise. CI/scripts can react.
```

**Why separate `temple:env` command:** `temple:runtime` reports state; `temple:env` reports the *required*-vs-present matrix so ops can debug "what's actually required here" without reading the code. They're different audiences (monitoring vs ops).

---

### Flow E — Artisan `php artisan temple:env`

```
1. artisan finds EnvironmentListCommand
       ▼
2. EnvironmentListCommand::handle()
       │ $env      = app(EnvironmentContract::class)
       │ $type     = $env->type()              // Local / Testing / Production
       │ $keys     = EnvValidator::requiredKeys($type)
       │ $missing  = EnvValidator::missingKeys($type)
       │
       │ foreach $keys as $key => $required:
       │     status = in_array($key, $missing) ? 'MISSING' : 'present'
       │     $this->line("[$status] $key " . ($required ? '(required)' : '(optional)'))
       │
       │ return $missing === [] ? self::SUCCESS : self::FAILURE
       ▼
3. Exit 0 if no missing required keys, 1 otherwise.
       Useful in pre-deploy scripts: artisan temple:env || exit 1
```

---

**Side-flow: env validation matrix source of truth**

The required-key matrix in `EnvValidator::requiredKeys()` is hard-coded in PHP, NOT in YAML or `config/runtime.php`. Rationale: doctrine says "ConfigurationContract reads from config()". The matrix itself is not configuration — it's a structural assertion that lives at the kernel layer. Putting it in `config/runtime.php` would invite ops to "tune" it, which would defeat its purpose. Hard-coded = immutable contract.

---

**Does this data flow section look right?** Specifically:
1. **Boot order in `bootstrap/providers.php`** — `Shared → Persistence → Runtime`. PersistenceServiceProvider must come BEFORE RuntimeServiceProvider because `RuntimeServiceProvider::register()` tags `DatabaseHealthProbe` which depends on `PersistenceAdapterContract`. Confirm.
2. **HealthController subscribes to `web` middleware group** — works because GET bypasses CSRF. Alternative: separate middleware group with no CSRF, no session. Recommend keeping `web` for now (simpler) unless you want a cleaner separation. Confirm.
3. **`/health` exempted from CSRF** is implicit. Some teams prefer explicit `->withoutMiddleware([VerifyCsrfToken::class])` for clarity. Confirm preference.
4. **`temple:env` exit code semantics** — `SUCCESS` when no required key is missing, `FAILURE` otherwise. Reasonable for pre-deploy hook. Confirm.
Section 4 (REWORKED) — Error Handling via Runtime Failure State Machine

### Doctrine alignment

Phase 1 already established (per `phase-1-deviations.md` line 238):

> ✓ State machines are the sole deciders of valid transitions

The Payments module owns three pure-function state-machine singletons (`PaymentStateMachine`, `DonationStateMachine`, `ReceiptStateMachine`) plus a `FailureStateService` that classifies failures via `FailureClassification` enum (`RecoverableTransient`, `RecoverableTerminal`, `TerminalInvalid`, `TerminalFraud`).

The Runtime module **must follow the same pattern** — otherwise we'd have two different failure-handling philosophies in the same codebase. So we instantiate:

```
App\Runtime\Failure\
  Contracts\FailureReportingContract.php
  Enums\FailureKind.php
  Enums\FailureState.php
  Enums\FailureEvent.php
  StateMachines\FailureStateMachine.php
  StateMachines\FailureTransitionResult.php
  ValueObjects\FailureRecord.php
  FailureRouter.php
  Handlers\BootFailureHandler.php
  Handlers\ProbeFailureHandler.php
  Handlers\CommandFailureHandler.php
  Handlers\HttpFailureHandler.php
```

Mirror of Phase 1's `Domain/StateMachines/` + `Services/FailureStateService.php` structure.

---

### New components

#### Enums

**`FailureKind`** — the *closed vocabulary* of failure categories the Runtime layer recognizes:
- `BootEnvMissing` — required env key not set (was E1)
- `PersistenceBindingFailed` — container cannot resolve `PersistenceAdapterContract` (was E9)
- `PersistenceQueryFailed` — `PersistenceAdapterContract::query/execute/transaction` returned `Result::failure` (was E8)
- `ProbeSubsystemDown` — health probe returned unhealthy (was E2/E3/E4)
- `HttpUnhandled` — uncaught `\Throwable` from a route (was E5)
- `CommandFailed` — artisan command detected unhealthy subsystem (was E6/E7)
- `FrameworkException` — Laravel-internal exception surfaced before our handlers run

**`FailureState`** — the lifecycle a failure moves through:
- `Observed` — captured at point of origin (try/catch or Result check)
- `Classified` — kind assigned, severity known
- `Recorded` — logged at appropriate level
- `Surfaced` — translated to user-visible response (HTTP body / CLI table / exception thrown)
- `Resolved` — terminal, no further action needed

**`FailureEvent`** — string-backed enum naming the events that drive transitions:
- `Observe` (→ Observed)
- `Classify` (→ Classified)
- `Record` (→ Recorded)
- `Surface` (→ Surfaced)
- `Resolve` (→ Resolved)

#### State machine

**`FailureStateMachine`** — pure transition table. **Sole decider** of valid state transitions for any `FailureKind`. Mirrors `PaymentStateMachine`'s pattern (from /StateMachines/ in Phase 1):

```
allowedTransitions = [
  'Observed'   => ['Classify'],
  'Classified' => ['Record'],
  'Recorded'   => ['Surface'],
  'Surfaced'   => ['Resolve'],
  'Resolved'   => [],   // terminal
]
```

Returns `FailureTransitionResult` value object: `nextState`, `handlerClass`, `logLevel`, `userMessage`. Pure function. No side effects. Singleton-bound.

#### Router

**`FailureRouter`** — the central service. **Sole coordinator** of failure workflow (mirrors `FailureStateService` in Phase 1):
- `report(FailureRecord $record): FailureTransitionResult` — public entry point. One call from any handler / probe / controller / artisan command.
- Internally loops: `observe → classify → record → surface → resolve`, asking the state machine for the next state at each step and dispatching to the named `HandlerClass` for side effects.
- Returns the final `FailureTransitionResult` so callers know the failure was fully resolved.

**`FailureReportingContract`** — interface so domain modules (Payments, etc.) can report failures to the runtime router without depending on `App\Runtime\Failure\FailureRouter` directly. Mirrors the way `ConfigurationContract` lets domain modules read config without depending on `LaravelConfiguration`.

#### Record + handlers

**`FailureRecord`** — value object carrying the facts: `id: ULID`, `kind: FailureKind`, `origin: string` (FQCN of the reporter), `message: string`, `previousState: FailureState`, `context: array<string, mixed>`, `occurredAt: DateTimeImmutable`.

**Four handlers**, one per side-effect surface:
- `BootFailureHandler` — receives a `BootEnvMissing` or `PersistenceBindingFailed` failure; throws `EnvironmentValidationException` with the combined actionable message (was E1, E9).
- `ProbeFailureHandler` — receives a `ProbeSubsystemDown` failure; converts to `HealthCheckResult::fail(...)` for the aggregator (was E2/E3/E4).
- `CommandFailureHandler` — receives a `CommandFailed` failure; returns Symfony exit code + prints the failure row (was E6/E7).
- `HttpFailureHandler` — receives an `HttpUnhandled` failure; renders the appropriate HTTP response (was E5).

Each handler is invoked by the `FailureRouter` based on what the state machine returns. Handlers are stateless and side-effect-only.

---

### How the existing E1–E10 scenarios flow through the new system

| # | Old behavior | New flow through FailureRouter |
|---|---|---|
| E1 | `BootProbe` throws directly | `BootProbe` calls `FailureRouter::report(FailureRecord(kind:BootEnvMissing, ...))` → router classifies → `BootFailureHandler` throws `EnvironmentValidationException` |
| E2 | `DatabaseHealthProbe` returns `fail` | Probe calls `router->report(FailureRecord(kind:ProbeSubsystemDown, ...))` → router resolves → returns `HealthCheckResult::fail` (probe already has it; router is the one place that decides whether to record a WARNING log) |
| E3 | `CacheHealthProbe` returns `fail` | Same as E2 |
| E4 | `QueueHealthProbe` returns `fail` | Same as E2 |
| E5 | Laravel 500 page | Uncaught `\Throwable` caught by Laravel's `ExceptionHandler` → handler delegates to `HttpFailureHandler` → `router->report(...)` → renders 500 with log level `ERROR` |
| E6 | `RuntimeStatusCommand` exits 1 | Command sees unhealthy subsystem → calls `router->report(FailureRecord(kind:CommandFailed, ...))` → `CommandFailureHandler` prints failure row + returns FAILURE exit code |
| E7 | `EnvironmentListCommand` exits 1 | Same as E6 |
| E8 | `Result::failure` returned | Service consumer sees `Result::failure` and decides to call `router->report(FailureRecord(kind:PersistenceQueryFailed, ...))`. Note: **`Result<T>` itself does NOT route through the failure system** — that's a return value, not a thrown failure. The CALLER of the service decides whether the `Result::failure` warrants reporting. This keeps `Result` as a pure return type. |
| E9 | Laravel throws binding error | Caught by `BootFailureHandler` → `router->report(FailureRecord(kind:PersistenceBindingFailed, ...))` → throws `EnvironmentValidationException` with binding specifics |
| E10 | Static guard short-circuits | Unchanged — the `BootProbe::$alreadyRan` guard is independent of the failure system |

---

### What this gives us

1. **Single decision vector** — `FailureStateMachine` is the only place that decides what happens to a failure. Adding a new failure kind = add a row to the enum + possibly extend the transition table. No scattering.
2. **Mirrors Phase 1 doctrine** — a developer who understands `PaymentStateMachine` + `FailureStateService` already understands `FailureStateMachine` + `FailureRouter`. Zero new mental model.
3. **Testable in isolation** — `FailureStateMachine` is a pure function (unit-test with no container). `FailureRouter` is testable with mock handlers. Handlers are testable with a mock router.
4. **Cross-domain reporting** — Phase 1's Payments `FailureStateService` can call `FailureReportingContract::report(...)` when a payment failure needs runtime-level surfacing (e.g., webhook handling failure → runtime log + alerting). One vocabulary, one router.
5. **Doctrine-clean** — `Result<T>` is preserved as a return type (E8 note); services don't throw across seams. The router is the seam-crossing mechanism for things that genuinely need cross-cutting action (logging, HTTP response, CLI exit).

---

### Cost

- ~12 new files in `App\Runtime\Failure\` instead of 1 exception class.
- New `FailureState`/`FailureKind`/`FailureEvent` enums.
- New `FailureReportingContract` interface.
- 5 new test files (state machine + router + 4 handlers).

But each file is small (~50-150 lines) and single-purpose.

---

**Does this rework look right?** Specifically:

1. **State machine mirrors `PaymentStateMachine` exactly** — pure transition table, `FailureTransitionResult` value object, singleton-bound. Confirm.
2. **`Result<T>` is NOT routed through the failure system** — it's a return value; the caller decides whether to report. Confirm this keeps `Result` semantics clean.
3. **`HttpFailureHandler` is invoked by Laravel's `ExceptionHandler`** — we register it via `bootstrap/app.php` exception handler customization. Confirm scope (one small customization, no full custom error pages yet).
4. **`FailureReportingContract` interface** — so Phase 1's `FailureStateService` can call into the runtime router without depending on `App\Runtime\Failure\FailureRouter` directly. Confirm the indirection.
5. **Cost (~12 files) is acceptable** — or do you want to compress this?
Section 5 — Testing

The Phase 0.25/1 test pattern (per `tests/Feature/SharedBindingsTest.php` and `tests/Feature/Payments/Infrastructure/InfrastructureTestCase.php`) is: **PHPUnit feature tests using the real Laravel container, with sqlite `:memory:` + RefreshDatabase**. The Runtime spec follows the same pattern. One addition: pure-function unit tests for `FailureStateMachine` and `EnvValidator` because they're deterministic.

---

### Test file inventory (15 files)

#### Unit tests (no container — pure logic)

| File | Tests | Why no container |
|---|---|---|
| `tests/Unit/Runtime/Failure/Enums/FailureKindTest.php` | Every `FailureKind` case has a handler class mapping, a default log level, a message template | Enums are pure data; nothing to resolve |
| `tests/Unit/Runtime/Failure/Enums/FailureStateTest.php` | State enum is closed (no missing states the state machine references) | Same — data only |
| `tests/Unit/Runtime/Failure/StateMachines/FailureStateMachineTest.php` | **The crown jewel test.** Every `(kind, currentState, clock) → FailureTransitionResult` mapping. Includes: allowed transitions, rejected transitions, deduplication within window, log-level inference per kind, handler dispatch decision. | `FailureStateMachine` is a pure function — no DI, no I/O |
| `tests/Unit/Runtime/Validation/EnvValidatorTest.php` | Required-key matrix per env; `missingKeys()` returns expected lists; `assert()` throws with combined actionable message | Validator logic is deterministic given a `ConfigurationContract` + `EnvironmentContract` mock |
| `tests/Unit/Runtime/Diagnostics/HealthCheckResultTest.php` | `ok()` / `fail()` named constructors; `isHealthy()`; latency stored as float | Value object — pure data |

#### Feature tests (full Laravel container, sqlite `:memory:`)

| File | Tests | Container strategy |
|---|---|---|
| `tests/Feature/Runtime/PersistenceBindingsTest.php` | `PersistenceAdapterContract` resolves to `LaravelDbAdapter`; `isConnected()` true against sqlite; `query("SELECT 1")` returns success; `RepositoryRegistryContract` resolves | Real Laravel container; uses `RefreshDatabase` trait |
| `tests/Feature/Runtime/RuntimeServiceProviderTest.php` | All `provides()` services resolve; `boot()` runs `BootProbe::assert()` exactly once; static `$alreadyRan` guard works across container resolutions | Real container; verify `alreadyRan()` after first boot |
| `tests/Feature/Runtime/BootProbeTest.php` | `assert()` succeeds in testing env (all keys present per `phpunit.xml`); throws `EnvironmentValidationException` in fake "production" env (override `APP_ENV`); idempotent (`alreadyRan` true after first call); throws with combined message listing all missing keys | Real container; use `Config::set('app.env', 'production')` in test, then `Config::set('app.key', null)` to trigger missing-key failure |
| `tests/Feature/Runtime/EnvValidatorTest.php` (feature version) | Integration with real `ConfigurationContract` + `EnvironmentContract`; full matrix walk in testing env confirms zero missing keys | Real container |
| `tests/Feature/Runtime/Http/HealthControllerTest.php` | `GET /health` returns 200 + JSON shape when all probes OK; returns 503 + degraded JSON when `DatabaseHealthProbe` mocked to return `fail()`; `/api/v1/ping` always returns 200 regardless of subsystem state | Real container; bind a `DatabaseHealthProbe` mock via `$this->app->bind(DatabaseHealthProbe::class, ...)` to force failure |
| `tests/Feature/Runtime/Console/RuntimeStatusCommandTest.php` | `php artisan temple:runtime` prints table with all subsystems OK in testing env; exit code 0; exit code 1 when `DatabaseHealthProbe` bound to failing mock | Real container; mock one probe; use `Artisan::call()` and assert exit code + `Artisan::output()` |
| `tests/Feature/Runtime/Console/EnvironmentListCommandTest.php` | `php artisan temple:env` prints required-vs-present table; exit 0 in testing env; exit 1 when `APP_KEY` is nulled | Real container; `Config::set('app.key', null)` triggers missing |
| `tests/Feature/Runtime/Failure/FailureRouterTest.php` | `report()` walks the full state lifecycle Observed → Resolved; calls each handler exactly once; returns final `FailureTransitionResult`; respects deduplication window | Real container; bind fake handlers that record invocations |
| `tests/Feature/Runtime/Failure/Handlers/BootFailureHandlerTest.php` | Throws `EnvironmentValidationException` with combined actionable message; called by router with the inferred transition result | Real container; mock router to deliver transition result |
| `tests/Feature/Runtime/Failure/Handlers/ProbeFailureHandlerTest.php` | Returns `HealthCheckResult::fail` with correct name/latency/detail; logs at WARNING level | Real container; `Log::shouldReceive('warning')` |
| `tests/Feature/Runtime/Failure/Handlers/CommandFailureHandlerTest.php` | Returns `Command::FAILURE` exit code; prints failure row | Real container |
| `tests/Feature/Runtime/Failure/Handlers/HttpFailureHandlerTest.php` | Renders correct HTTP status; correct JSON shape; logs at inferred level | Real container |
| `tests/Feature/Runtime/Failure/FailureReportingContractTest.php` | **The routing-membrane guarantee.** Static-analysis-flavored test: greps `app/` for direct calls to handlers (e.g., `new BootFailureHandler()`) outside `FailureRouter` and fails the test if any are found. Verifies the contract is the only entry point. | Real container; uses `File::allFiles(app_path())` + `Str::contains()` |
| `tests/Feature/Runtime/Diagnostics/HealthProbeTest.php` | Data-provider test against all 3 probes; each returns `HealthCheckResult` with positive latency; `DatabaseHealthProbe` uses `PersistenceAdapterContract` (verify via container binding — replace with a spy adapter, assert the spy was called) | Real container; bind a spy `PersistenceAdapterContract` |

---

### Container override strategy

The Payments module uses `tests/Feature/Payments/Infrastructure/InfrastructureTestCase.php` to set up shared test fixtures. The Runtime module creates an analogous base class:

```
tests/Feature/Runtime/RuntimeTestCase.php  (NEW — base for runtime feature tests)
```

Provides:
- `RefreshDatabase` trait (already standard)
- Default bindings for all `HealthProbe`s (real ones, sqlite-backed) so most tests don't need to mock
- Helper `failDatabaseProbe()` / `failCacheProbe()` / `failQueueProbe()` that swaps one probe for a failing fake
- Helper `setMissingEnvKeys(['APP_KEY', 'DATABASE_URL'])` that nulls env keys for BootProbe negative tests

Most Runtime feature tests extend `RuntimeTestCase` and stay short.

---

### What does NOT get tested in this spec

1. **Phase 3/4 routes** (public site, admin) — not in scope.
2. **Payments integration** — `PaymentsServiceProvider` has a broken import (already flagged). Phase 1 closure concern.
3. **End-to-end Neon connectivity** — no real Neon credentials in CI. SQLite covers all the integration logic. Neon specifics come in the next spec.
4. **Real Redis behavior** — `CACHE_STORE=array` in `phpunit.xml` covers the probe path. Redis-failure scenarios use mocks.
5. **Performance / load** — not in scope for a wiring spec.
6. **Browser-facing UI** — no browser tests. Health endpoint is JSON-only.

---

### Coverage targets

- **Line coverage:** 90%+ on Runtime module classes (they're small and the doctrine says "test everything").
- **Branch coverage:** 100% on `FailureStateMachine` (deterministic logic must be exhaustively covered).
- **Test count estimate:** ~15 files × ~5 tests/file ≈ 75 new tests. Total project test count goes from ~300 to ~375.

---

### CI integration (out of scope but worth flagging)

The `phpunit.xml` already drives `APP_ENV=testing` + sqlite — no change needed. PHPStan (`phpstan.neon`) should remain clean (the codebase has been passing per Phase 1). Pint formatting stays applied.

---

**Does this testing section look right?** Specifically:

1. **15 test files total** (5 unit + 10 feature) — proportionate to the ~25 production files, or do you want more / fewer?
2. **`FailureStateMachineTest` as the crown jewel** — exhaustive mapping of `(kind, state)` → `FailureTransitionResult`. Confirm this is the right priority.
3. **`FailureReportingContractTest`** as the static-analysis-flavored test that enforces the routing-membrane guarantee — slightly unusual test, but it catches the "someone bypassed the router" failure mode. Confirm.
4. **`RuntimeTestCase`** as the shared base class — mirrors Payments' `InfrastructureTestCase` pattern. Confirm.
5. **No browser / Neon / Redis-real tests** — confirm these are correctly deferred.

Approve Section 5 → I write the spec doc → we review → then the same shape for Neon PostgreSQL.
