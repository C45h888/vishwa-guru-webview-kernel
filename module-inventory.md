# Module Inventory — Phase 0.25 Kernel

This document maps every contract, value object, enum, exception, and support
class that constitutes the architectural kernel. It exists so future modules
(and future engineers) can find the right interface without grep-diving.

All paths are relative to the repository root.

---

# Shared Module — `app/Shared/`

The Shared module is the architectural kernel. Every other module depends on it.
Nothing in Shared depends on anything outside Shared.

## Contracts — `app/Shared/Contracts/`

| File | Purpose |
|---|---|
| `RepositoryContract.php` | Base contract every repository implements: save, find, findOrFail, findBy, delete, count, paginate |
| `ServiceContract.php` | Marker interface for service classes |
| `ModuleContract.php` | Marker interface for module roots |
| `ModuleServiceProviderContract.php` | Marker interface for module service providers |
| `ValueObjectContract.php` | equals(), value() |
| `ConfigurationContract.php` | get, string, integer, boolean, has, all |
| `EnvironmentContract.php` | get, isLocal, isProduction, isTesting, type, environment |

## Enums — `app/Shared/Enums/`

| File | Cases |
|---|---|
| `EnvironmentType.php` | Local, Testing, Production, CI |
| `LifecycleStage.php` | Constitution, PreFoundation, DatabaseArchitecture, FinancialPlatform, PlatformFoundation, PublicPlatform, Administration, Production |

## Value Objects — `app/Shared/ValueObjects/`

| File | Purpose |
|---|---|
| `AbstractValueObject.php` | Base class for VOs (compares by value) |
| `Identifier.php` | ULID-backed unique identifier |
| `Money.php` | ISO 4217 currency + minor units, immutable arithmetic |

## Configuration — `app/Shared/Configuration/`

| File | Purpose |
|---|---|
| `AbstractConfiguration.php` | Base configuration class |
| `LaravelConfiguration.php` | ConfigurationContract backed by Laravel `config()` |
| `NamespacedConfiguration.php` | Wraps another contract with a key prefix |
| `ConfigurationRegistry.php` | Multi-namespace config lookup |

## Environment — `app/Shared/Environment/`

| File | Purpose |
|---|---|
| `EnvironmentResolver.php` | Resolves APP_ENV → EnvironmentType |
| `LaravelEnvironment.php` | EnvironmentContract backed by Laravel |

## Exceptions — `app/Shared/Exceptions/`

| File | Purpose |
|---|---|
| `BaseException.php` | Root: code + context bag |
| `ConfigurationException.php` | Misconfiguration detected at runtime |
| `DomainException.php` | Business-rule violation |
| `InfrastructureException.php` | External system failure |
| `ValidationFailedException.php` | Input rejected |

## Support — `app/Shared/Support/`

| File | Purpose |
|---|---|
| `Clock.php` | Interface (now/timestamp/timezone) |
| `SystemClock.php` | Wall-clock production implementation |
| `FrozenClock.php` | Test-time pin/advance implementation |
| `IdentifierGenerator.php` | next(): string contract |
| `UlidGenerator.php` | ULID implementation, also has static helpers |
| `Result.php` | Success/Failure monad |

## Provider — `app/Shared/Providers/`

| File | Bindings |
|---|---|
| `SharedServiceProvider.php` | ConfigurationContract → LaravelConfiguration, EnvironmentContract → LaravelEnvironment, Clock → SystemClock, IdentifierGenerator → UlidGenerator, ConfigurationRegistry → self |

---

# Payments Module — `app/Payments/`

The Payments module's Phase 0.25 surface is **contracts only**. No provider
implementation, no HTTP wiring, no business logic.

## Contracts — `app/Payments/Contracts/`

| File | Purpose |
|---|---|
| `PaymentGatewayContract.php` | Authoritative gateway: initialize, verify, capture, refund |
| `PaymentProviderContract.php` | Provider capability declaration: name, supported currencies, min/max amount, priority |
| `PaymentVerificationContract.php` | Webhook signature verification (HMAC-style) |
| `FailureStateContract.php` | Immutable record of terminal payment failures |
| `ReceiptGenerationContract.php` | Generates receipts after verified payments |

## Enums — `app/Payments/Enums/`

| File | Cases |
|---|---|
| `TransactionStatus.php` | Initialized, Pending, Authorized, Captured, Settling, Settled, Failed, Refunded, PartiallyRefunded, Disputed, Cancelled, Expired |
| `Currency.php` | INR, USD, EUR, GBP, AUD, CAD, SGD, AED, JPY |

## Value Objects — `app/Payments/ValueObjects/`

| File | Purpose |
|---|---|
| `PaymentRequest.php` | Immutable payment initialization payload |

---

# Persistence Module — `app/Persistence/`

The Persistence module's Phase 0.25 surface is **contracts only**. No migrations,
no Eloquent models, no PDO.

## Contracts — `app/Persistence/Contracts/`

| File | Purpose |
|---|---|
| `EntityContract.php` | Marker for persistable domain entities |
| `RepositoryRegistryContract.php` | Entity-type → repository-class lookup |
| `PersistenceAdapterContract.php` | Connection + transaction abstraction |

## Value Objects — `app/Persistence/ValueObjects/`

| File | Purpose |
|---|---|
| `EntityId.php` | Type-prefixed ULID: `{entity_type}_{ulid}` |

---

# Laravel Skeleton — Root + `config/` + `routes/` + `tests/`

| File | Purpose |
|---|---|
| `composer.json` | Laravel 10.x, PHP 8.2+, PSR-4 `App\` |
| `artisan` | Laravel CLI entry point |
| `bootstrap/app.php` | Application bootstrap (Http/Console/ExceptionHandler singletons) |
| `public/index.php` | HTTP entry point |
| `routes/web.php` | Health check + web routes |
| `routes/console.php` | Inspire stub |
| `routes/api.php` | Empty API route file |
| `app/Http/Kernel.php` | HTTP middleware stack |
| `app/Console/Kernel.php` | Console kernel |
| `app/Providers/AppServiceProvider.php` | Empty placeholder |
| `app/Providers/RouteServiceProvider.php` | Route loading |
| `config/app.php` | App config + provider list |
| `config/database.php` | PostgreSQL default + MySQL/SQLite fallbacks |
| `config/services.php` | Razorpay/PayPal/Neon stubs (no keys) |
| `config/shared.php` | Shared module config (default currency, identifier strategy) |
| `config/{auth,broadcasting,cache,cors,filesystems,hashing,logging,mail,queue,session}.php` | Laravel 10 default scaffolding |
| `phpunit.xml` | Test runner config (in-memory SQLite, testing env) |
| `phpstan.neon` | Static analysis config (level=max) |
| `.env.example` | Environment template (no secrets) |
| `.gitignore` | Standard Laravel ignores + `.phpunit.cache` |
| `tests/TestCase.php` | Base TestCase |
| `database/{factories,migrations,seeders}/.gitkeep` | Phase 0.5+ placeholders |
| `storage/{app,framework/cache,framework/sessions,framework/testing,framework/views,logs}/.gitkeep` | Runtime placeholders |

---

# Constitutional Documents — Repo Root

| File | Purpose |
|---|---|
| `README.md` | Repo overview |
| `agents.md` | AI Engineering Constitution |
| `architecture.md` | Architectural philosophy + dependency rules |
| `domain-modules.md` | Business entity model (Donation, Payment, Receipt, ...) |
| `modules.md` | Module ownership declaration |
| `payment-processing.md` | Canonical payment workflow + lifecycle |
| `roadmap.md` | Phase-by-phase execution plan |
| `security.md` | Security posture |
| `module-inventory.md` | This file |

---

# Phase 0.25 Exit Criteria — Verification

| Criterion | Status | Evidence |
|---|---|---|
| Architectural kernel exists | ✓ | `app/Shared/` populated |
| All contracts defined | ✓ | 14 contract files (Shared + Payments + Persistence) |
| Shared abstractions exist | ✓ | Contracts, VOs, enums, support, config, env, exceptions |
| Payment abstractions exist | ✓ | 5 contracts + 2 enums + PaymentRequest |
| Persistence abstractions exist | ✓ | 3 contracts + EntityId |
| No business logic | ✓ | No services, no controllers, no workflows |
| No database schema | ✓ | No migrations; `.gitkeep` only |
| No payment provider wired | ✓ | `config/services.php` is the only place provider names appear, all `[REDACTED]` |

**Tests:** 71 passing, 0 failing, 0 errors (PHPUnit 10.5.64, PHP 8.3.32).