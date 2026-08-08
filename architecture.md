# ARCHITECTURE.md

# Temple Trust Management System Architecture

## Purpose

This document defines the architectural foundation of the Temple Trust Management System. It serves as the authoritative engineering reference for the repository and establishes the principles, boundaries, and implementation standards that govern the application.

The objective of this document is not merely to describe the software structure, but to ensure that every future contribution preserves the architectural integrity of the system. Every developer and AI coding agent working within this repository should understand the concepts defined here before implementing new functionality.

---

# Architectural Philosophy

The Temple Trust Management System follows a **backend-first, service-oriented architecture** built on Laravel. The backend is considered the authoritative implementation of the application's business rules and operational workflows.

The system is designed around the principle that business processes should exist independently of the presentation layer. User interfaces are responsible only for presenting workflows that have already been defined by the backend. Business rules must never originate from Blade templates, JavaScript components, or client-side logic.

Financial integrity, security, maintainability, and architectural clarity are considered the four foundational pillars of the application. Every implementation decision should reinforce these principles rather than optimize solely for development speed.

Laravel is intentionally used as the primary application framework because it provides mature solutions for authentication, routing, validation, authorization, queues, scheduling, notifications, and database interaction. The project favors Laravel conventions over unnecessary custom abstractions whenever those conventions satisfy the application's requirements.

---

## Per-kernel Documentation

The application's `app/` directory is partitioned into ten kernels (four
content kernels + one money kernel + five infrastructure / foundation
kernels). Every kernel directory contains a `Kernel.md` landing page
following a fixed five-section template: Boundaries → Contracts →
Providers → FSMs → Tests. See [`README.md` § Repository Structure](README.md#repository-structure)
for the table; the canonical per-kernel landing pages are:

- [`app/Campaigns/Kernel.md`](app/Campaigns/Kernel.md)
- [`app/Cms/Kernel.md`](app/Cms/Kernel.md)
- [`app/Events/Kernel.md`](app/Events/Kernel.md)
- [`app/Gallery/Kernel.md`](app/Gallery/Kernel.md)
- [`app/Payments/Kernel.md`](app/Payments/Kernel.md)
- [`app/Persistence/Kernel.md`](app/Persistence/Kernel.md)
- [`app/Redis/Kernel.md`](app/Redis/Kernel.md)
- [`app/Queue/Kernel.md`](app/Queue/Kernel.md)
- [`app/Runtime/Kernel.md`](app/Runtime/Kernel.md)
- [`app/Shared/Kernel.md`](app/Shared/Kernel.md)

The `Kernel.md` discipline is codified in [`agents.md` § Kernel.md
Discipline](agents.md#kernelmd-discipline). When the architecture of a
kernel changes, its `Kernel.md` updates alongside the code change.

---

# Architectural Objectives

The architecture has been designed with the following long-term objectives:

* Preserve financial integrity throughout every payment workflow.
* Keep business logic centralized and independent from presentation.
* Maintain clear domain boundaries.
* Favor modular organization over framework-centric organization.
* Ensure code remains understandable by both developers and AI agents.
* Support long-term maintainability.
* Minimize architectural complexity.
* Keep infrastructure replaceable without affecting business logic.
* Build a stable operational platform rather than a collection of independent pages.

---

# System Overview

The application is implemented as a modular service-oriented Laravel application.

Business domains remain isolated from one another while collaborating through explicit service contracts.

Controllers coordinate HTTP requests.

Services execute business workflows.

Repositories perform persistence operations.

Eloquent models represent the underlying data.

The database stores the authoritative operational state of the application.

The frontend is responsible only for rendering business workflows that have already been validated by the backend.

---

# Core Architectural Principles

## Backend First Development

Development always begins with backend implementation.

Business workflows, validation rules, authorization policies, payment processing, service contracts, and persistence are completed before frontend development begins.

Frontend components should never dictate business behavior.

Instead, they consume stable backend workflows.

---

## Service-Oriented Architecture

The service layer is the architectural center of the application.

Every significant business workflow belongs inside a dedicated service.

Examples include:

* Donation Service
* Payment Service
* Event Service
* Gallery Service
* CMS Service
* Authentication Service
* Notification Service

Services encapsulate business logic and coordinate interactions between domains.

Business rules must never be implemented inside controllers or Blade templates.

---

## Thin Controllers

Controllers exist solely to translate HTTP requests into business operations.

Controllers are responsible for:

* Receiving validated requests.
* Calling the appropriate service.
* Returning responses.
* Handling redirects where appropriate.

Controllers must not:

* Contain business logic.
* Execute payment verification.
* Construct complex database queries.
* Implement business rules.

---

## Repository Layer

Repositories are responsible for all persistence operations.

Business services should not directly manipulate Eloquent models for complex persistence workflows.

Instead, services communicate with repositories that encapsulate database interactions.

This separation keeps business logic independent from storage implementation and allows persistence strategies to evolve without changing domain logic.

---

# Domain Organization

The repository is organized around business capabilities rather than Laravel's default technical folders.

Each domain owns its own controllers, services, repositories, requests, policies, and related resources.

The initial domains are:

* Authentication
* Payments
* Donations
* Events
* Gallery
* CMS
* Notifications (Email)
* Shared

Payments intentionally exist as an independent domain rather than being embedded within Donations. A donation is a business concept, while payment processing is an infrastructure concern. This separation preserves clear domain boundaries and improves maintainability.

---

# Service Communication

Business workflows frequently span multiple domains.

Services are therefore permitted to coordinate with other services when implementing complete business transactions.

A typical donation workflow may follow this sequence:

Donation Service

↓

Payment Service

↓

Receipt Service

↓

Notification Service

Each service remains responsible only for its own domain while collaborating through clearly defined contracts.

Domains must not bypass services to directly manipulate another domain's internal implementation.

---

# Financial Integrity

Financial integrity is considered one of the primary architectural principles of the application.

No payment is considered successful until it has been cryptographically verified through the payment gateway's callback mechanism.

The canonical workflow is:

Donation Request

↓

Input Validation

↓

Payment Initialization

↓

Gateway Processing

↓

Webhook Callback

↓

Signature Verification

↓

Database Commit

↓

Receipt Generation

↓

Email Notification

↓

Audit Record

Only after successful verification may the application persist financial records or generate official receipts.

The application must never rely solely on client-side success responses.

---

# Database Philosophy

PostgreSQL serves as the authoritative persistence layer of the application.

Business entities, financial transactions, operational records, and audit history are stored within the relational database.

The database represents the verified operational state of the system.

Application services validate and verify business operations before persistence occurs.

No financial information should be committed until verification has completed successfully.

## Neon PostgreSQL Connection

The application targets [Neon](https://neon.tech) as its production PostgreSQL provider. The runtime ships a dedicated `neon` connection preset in `config/database.php` that enforces `sslmode=require` and sets `application_name=temple-trust` for query observability.

### Role Separation

Three Neon roles are defined in code as `App\Persistence\Neon\ValueObjects\NeonRole`:

| Role | Grants | Used by | Doctrine rationale |
|---|---|---|---|
| `Owner` | DDL (CREATE/ALTER/DROP) + CREATE EXTENSION | `php artisan migrate` | Schema migrations require DDL. Never used at runtime. |
| `App` | SELECT/INSERT/UPDATE/DELETE on domain tables | Runtime HTTP requests | Least privilege — runtime has no DDL rights. |
| `Reader` | SELECT only | Future read replicas / reporting | V2 feature; reserved for V1. |

Roles are granted at the Neon console under **Settings → Roles**. The active role is parsed from the `NEON_ROLE` env key and surfaced in `php artisan temple:neon:ping` output. **Using `owner` at runtime violates least-privilege** — the runtime must run as `app`.

### Extensions

The canonical schema installs three PostgreSQL extensions via `CREATE EXTENSION IF NOT EXISTS`:

- `pgcrypto` — cryptographic functions (UUIDs, digests)
- `citext` — case-insensitive text (donor emails)
- `btree_gist` — enables `EXCLUDE` constraints (used by `static_pages.is_homepage`)

These are installed by the schema migration (`database/migrations/2026_07_16_000001_k_bootstrap_create_v1_schema_postgres.php` running `schema-neon/V1-schema.sql`). The `Owner` role must have `CREATE EXTENSION` privilege. The runtime probe (`temple:neon:ping` and `/health` JSON) verifies these extensions are present and reports `neon` subsystem health accordingly.

### Connection Preset Doctrine

The `config/database.php` `neon` block is the only sanctioned way to reach production data. It is bound at runtime via `DB_CONNECTION=neon`. The `pgsql` block is reserved for local development (Docker Postgres without TLS). The static `'sslmode' => 'prefer'` that previously lived in the `pgsql` block has been removed — it silently downgraded `sslmode=require` when a Neon `DATABASE_URL` was used.

---

# File Storage Strategy

The database stores metadata describing uploaded files rather than binary content.

Large files such as:

* Donation receipts
* Temple images
* Gallery assets
* Videos
* Trust documents
* Certificates

are managed through Laravel's storage abstraction.

The database records:

* File identifier
* Storage path
* Hash
* Related entity
* Upload metadata

This strategy preserves database performance while maintaining complete traceability.

---

# Notification Architecture

Notifications remain independent from business workflows.

Business services communicate with the Notification Service rather than directly interacting with email providers.

Initially the application supports:

* Email notifications

Future support may include:

* WhatsApp
* SMS

The Notification Service provides a stable abstraction that prevents changes to communication providers from affecting business logic.

---

# Security Principles

Security is treated as a foundational architectural concern.

The application follows these principles:

* Validate all incoming data.
* Enforce authorization before business execution.
* Protect financial operations through gateway verification.
* Never trust client-side state.
* Store secrets outside source control.
* Apply Laravel's native CSRF protection.
* Use framework-native authentication and authorization.
* Record important operational events.
* Follow the principle of least privilege.

Business logic should always execute within trusted backend services.

---

# Audit Philosophy

The system maintains an auditable operational history.

Important business events should generate audit records describing:

* Actor
* Action
* Entity
* Entity Identifier
* Timestamp
* Relevant metadata

Financial operations should always be traceable.

The database therefore functions as both the operational data store and the primary audit surface for the application.

---

# Technology Stack

Application Framework

Laravel

Programming Language

PHP

Frontend

Blade Templates

Styling

Tailwind CSS

Frontend Interactivity

Alpine.js

Database

Neon PostgreSQL

ORM

Laravel Eloquent

Primary Payment Gateway

Razorpay

Secondary Payment Gateway

PayPal

Notifications

Email

Caching and Queues

Redis (4 logical DBs: app=0, cache=1, queue=2, session=3; `ext-redis` PHP extension required)

---

# Dependency Rules

Dependencies always move inward toward the business layer.

Presentation depends on services.

Services depend on repositories.

Repositories depend on Eloquent and PostgreSQL.

Business domains communicate only through service contracts.

No module should directly manipulate another module's internal implementation.

This preserves explicit ownership and prevents architectural coupling.

---

# Current Scope

The current implementation targets a **single temple trust**.

The architecture intentionally avoids premature multi-tenant abstractions.

Future support for multiple trusts or organizations may be introduced through deliberate architectural evolution rather than speculative design.

Every architectural decision made today optimizes for simplicity, maintainability, and correctness within a single deployment.

---

# Engineering Standards

The following rules apply throughout the repository:

* Business logic belongs exclusively in services.
* Controllers remain thin.
* Blade templates remain presentation-only.
* Payment verification is mandatory before persistence.
* Every schema modification uses Laravel migrations.
* Repository methods encapsulate persistence.
* Domain boundaries must remain explicit.
* Framework conventions take precedence over unnecessary abstractions.
* Documentation should evolve alongside implementation.
* Maintainability always takes precedence over cleverness.

---

# Closing Statement

The Temple Trust Management System is intended to become the operational software platform supporting the trust's day-to-day activities. The architecture therefore prioritizes reliability, financial integrity, security, and long-term maintainability over unnecessary complexity.

Every feature added to the system should reinforce these architectural principles. Contributions should extend the existing design rather than circumvent it, ensuring that the application remains coherent, understandable, and dependable throughout its lifetime.


---

# Runtime Wiring — Phase 2

This section documents how the framework pieces connect at runtime. It
exists so future agents can answer "what runs at boot, in what order,
with what side-effects?" without grep-diving.

## Provider boot order

The provider list in `bootstrap/providers.php` is the source of truth.
Order is significant — later providers may depend on bindings declared
by earlier ones.

| # | Provider | Owns |
|---|---|---|
| 1 | `App\Providers\AppServiceProvider` | Application-wide bindings (currently empty placeholder) |
| 2 | `App\Shared\Providers\SharedServiceProvider` | ConfigurationContract, EnvironmentContract, Clock, IdentifierGenerator, ConfigurationRegistry |
| 3 | `App\Persistence\Providers\PersistenceServiceProvider` | PersistenceAdapterContract → LaravelDbAdapter, RepositoryRegistryContract |
| 4 | `App\Runtime\Providers\RuntimeServiceProvider` | HealthProbes, HealthCheckAggregator, BootProbe, EnvValidator, KernelSnapshot, RuntimeStatusCommand, EnvironmentListCommand, HealthController, PingController, FailureRouter |
| 5 | `App\Redis\Providers\RedisServiceProvider` | RedisConnectorContract → LaravelRedisConnector |
| 6+ | (Future module providers) | Payments, Donations, Notifications, CMS, Gallery, Events |

Doctrine rule: when adding a new module provider, APPEND it to the list
without reordering existing entries. Existing bindings must remain
stable.

## Database connections

| Connection | Driver | Purpose | Env vars |
|---|---|---|---|
| `pgsql` (default) | PostgreSQL via PDO | Neon PostgreSQL — authoritative store | DATABASE_URL or DB_HOST/PORT/etc. |
| `sqlite` | SQLite in-memory | Testing only | DB_CONNECTION=sqlite, DB_DATABASE=:memory: |
| `redis.default` | phpredis | App keys (idempotency, webhook dedupe, locks) | REDIS_HOST, REDIS_DB=0 |
| `redis.cache` | phpredis | Laravel Cache::* facade | REDIS_CACHE_DB=1 |
| `redis.queue` | phpredis | Laravel Queue::* facade | REDIS_QUEUE_DB=2 |
| `redis.session` | phpredis | Reserved for Phase 4 | REDIS_SESSION_DB=3 |

## Cache store resolution

`config/cache.php` default = `redis`. The phpunit.xml override is `array`
for testing isolation. The fallback chain (Redis down → file cache)
happens at the Laravel CacheManager level — service code does not need
to handle it.

## Queue connection resolution

`config/queue.php` default = `redis`. The phpunit.xml override is `sync`.
Failed-job tracking uses the `database-uuids` driver (writes to the
`failed_jobs` table; migration lands with the first Phase 1 closure
job class).

## Health and introspection endpoints

| Endpoint | Purpose | Implemented by |
|---|---|---|
| `GET /health` | Subsystem readiness (DB, cache, queue) | Runtime/Http/Controllers/HealthController |
| `GET /api/v1/ping` | Process liveness (no subsystem checks) | Runtime/Http/Controllers/PingController |

| Command | Purpose |
|---|---|
| `php artisan temple:runtime` | Print runtime status table; exits non-zero if any subsystem probe fails |
| `php artisan temple:env` | Print required env keys per environment |
| `php artisan temple:redis:info` | Dump Redis INFO + keyspace stats for one or all configured connections. Scheduled every minute via `app/Console/Kernel.php`. |

## Failure routing

Every failure in the Runtime module flows through `FailureRouter`. No
class in the runtime tree instantiates a handler directly — handlers
are bound by the FailureRouter and dispatched based on `FailureKind`.
See `app/Runtime/Failure/` for the full state machine and routing
membrane.


---

# Queue Configuration — Phase 2

## Queue substrate

Laravel's queue subsystem is wired against Redis DB 2 (the
`redis.queue` connection configured in the Redis turn). Failed
jobs and batch metadata live on Postgres (`failed_jobs`,
`job_batches` tables).

The `QUEUE_CONNECTION=redis` default is set in `config/queue.php`.
The `phpunit.xml` override is `sync` for testing isolation.

## Retry policy

The retry contract is doctrine-driven and env-configurable:

| Path | tries | backoff | Use case |
|---|---|---|---|
| General-purpose | 3 | [10, 60, 300] seconds | Notifications, audit archival, webhooks |
| Financial | 1 | [0] | Receipts tied to verified payments, donation state transitions |

Set in `config/queue.php` under `general` and `financial` blocks.
Override at the class level in subclasses of `AbstractQueuedJob`:

```php
final class ReceiptPdfGenerationJob extends AbstractQueuedJob
{
    public int $tries = 1;             // financial — fail-fast
    public array $backoff = [0];
    public function handle(ReceiptService $receipts): void
    {
        $receipts->generatePdf($this->receiptId);
    }
}
```

Doctrine: financial correctness > convenience. A payment has been
verified by the gateway; if the receipt job fails, retrying
silently is worse than surfacing to ops via the `failed_jobs` table.

## Domain service surface

Service code depends on `QueueConnectorContract`, never on `Queue::`
facade. Same doctrine as `RedisConnectorContract` and
`PersistenceAdapterContract`. This keeps the queue topology in
one place and makes tests substitute a fake without booting a worker.

```php
final class PaymentService
{
    public function __construct(
        private readonly QueueConnectorContract $queue,
        ...
    ) {}

    public function complete(DonationId $id): void
    {
        // ... synchronous payment verification ...
        $job = QueuedJob::financial(
            jobClass: ReceiptPdfGenerationJob::class,
            payload: ['donationId' => $id->value()],
            queue: 'receipts',
        );
        $this->queue->dispatch($job);
    }
}
```

## Worker runtime

In production: a separate container runs `php artisan queue:work redis
--tries=3 --backoff=10,60,300 --max-time=3600 --sleep=3`. The
doctrine: HTTP serving and async processing are different concerns.
A stuck worker cannot take down the donation form.

Failed-job retention: 30 days via `php artisan queue:prune-failed
--hours=720`, scheduled weekly.

## Failure semantics

| Path | Behavior on Redis down |
|---|---|
| `ping()` | Returns false (never throws). Doctrine-critical for HealthProbe + fallback paths. |
| `size(?queue)` | Returns -1 (sentinel). Never throws. |
| `listFailed(limit)` | Returns []. Never throws. |
| `retryFailed(uuid)` | Returns false. Never throws. |
| `dispatch(job)` | **Throws**. Doctrine-critical: caller MUST know the job didn't enqueue. |

Doctrine: payments must surface failure, not hang. Cache and queue
paths fail-open; payment paths fail-closed.

## Health and introspection

| Surface | Purpose |
|---|---|
| `php artisan temple:queue:stats` | On-demand ops view: driver, depth, failed count, recent failures |
| `php artisan temple:runtime` | Auto probe includes queue health via QueueHealthProbe |
| `GET /health` | Returns queue subsystem status in JSON |
