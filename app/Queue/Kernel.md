# Queue Kernel

> One-screen navigation map for the `Queue` kernel.
> Read order: Boundaries → Contracts → Providers → FSMs → Module class → Tests.

## Boundaries

**Owns:** the queue connection surface. Single contract:
`QueueConnectorContract`. Owns the connection factory, the artisan
commands under `app/Queue/Console/`, and the queue value objects
(`app/Queue/ValueObjects/` — payload envelope, retry policy).

**Does NOT own:** job definitions (Payments owns its jobs — `tests/Unit/Payments/Jobs/`),
queueing policy (consuming kernels declare their own retry/timeout
configuration), or async orchestration.

**Outbound edges:**
- Into Redis via `App\Redis\Contracts\RedisConnectorContract` (queue
  connection reads Redis-backed queue names).

## Contracts

| Contract | FQCN | Direction |
|---|---|---|
| QueueConnectorContract | `App\Queue\Contracts\QueueConnectorContract` | Single queue surface (consumed by Payments jobs + Runtime diagnostics) |

Single public contract. Tight surface; same design philosophy as
Redis — substrate exposes only the seam consumers need.

## Providers

`App\Queue\Providers\QueueServiceProvider` — pinned position in the
provider order: Shared → Persistence → Runtime → Redis → **Queue** →
Payments → Cms → Campaigns → Gallery → Events. Boots after Redis so
the queue connection can read Redis-backed names; before Payments
because Payments jobs are dispatched in `PaymentsServiceProvider::register()`.

Bindings:
- `QueueConnectorContract → QueueConnector` (singleton)

## FSMs

**None.** Queue is a substrate with no business lifecycle.

## Module class

**Intentionally absent.** No `QueueModule.php`.

**Why:** Same as Persistence and Redis — substrate, not business.

## Tests

(No test directory under `tests/Unit/Queue/` or `tests/Feature/Queue/`
as of Phase 1 of this restructure. Coverage is exercised through the
consuming kernels' integration tests — Payments async-job tests in
`tests/Unit/Payments/Jobs/`.)