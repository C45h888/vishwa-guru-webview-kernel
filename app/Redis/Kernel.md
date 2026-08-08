# Redis Kernel

> One-screen navigation map for the `Redis` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** the Redis connection surface. Single contract:
`RedisConnectorContract`. Owns the connection factory and the artisan
commands under `app/Redis/Console/`.

**Does NOT own:** idempotency logic (Payments consumes the connector and
owns the `SETEX` dedupe policy), caching policy (Cms owns the
resolved-page cache), or queueing (Queue kernel owns its own substrate).

**Outbound edges:**
- None — Redis is a substrate.

## Contracts

| Contract | FQCN | Direction |
|---|---|---|
| RedisConnectorContract | `App\Redis\Contracts\RedisConnectorContract` | Single Redis surface (consumed by Payments idempotency, Cms resolved-page cache, Runtime diagnostics) |

Single public contract. Tight surface; this is by design — Redis is a
dumb substrate, business policy lives in the consuming kernel.

## Providers

`App\Redis\Providers\RedisServiceProvider` — pinned position in the
provider order: Shared → Persistence → Runtime → **Redis** → Queue →
Payments → Cms → Campaigns → Gallery → Events. Boots after Runtime so
the connection can read runtime configuration; before Queue + Payments
because both need Redis access at boot.

Bindings:
- `RedisConnectorContract → RedisConnector` (singleton; reads
  `REDIS_*` env vars via Shared's `ConfigurationContract`)

## FSMs

**None.** Redis is a substrate with no business lifecycle.

## Module class

**Intentionally absent.** No `RedisModule.php`.

**Why:** Same as Persistence — the `ModuleContract` encodes business
capability, not infrastructure capability. Redis is consumed by other
kernels via `RedisConnectorContract`; adding a `Module` declaration
would conflate substrate with business.

## Tests

(No test directory under `tests/Unit/Redis/` or `tests/Feature/Redis/`
as of Phase 1 of this restructure. Coverage is exercised through the
consuming kernels' integration tests — Cms resolved-page-cache tests,
Payments idempotency tests.)