<?php

/*
|--------------------------------------------------------------------------
| Temple Trust Management System — Provider Registration
|--------------------------------------------------------------------------
|
| This file is the canonical source of truth for service provider order.
| Providers listed here are loaded by bootstrap/app.php and executed
| during the Application::register phase.
|
| Order is significant:
|   1. AppServiceProvider         — application-wide bindings
|   2. SharedServiceProvider      — base contracts and abstractions
|   3. PersistenceServiceProvider — kernel-level persistence contracts
|   4. RuntimeServiceProvider     — runtime infrastructure (health, env,
|                                   failure state machine, commands)
|   5. RedisServiceProvider       — Redis connector contract wiring
|                                   (DB 0 app / 1 cache / 2 queue / 3 session)
|   6. QueueServiceProvider       — Queue connector contract wiring
|                                   (Laravel queue manager; failed_jobs +
|                                    jobs + job_batches tables in postgres)
|   7. PaymentsServiceProvider    — Phase 1 Financial Kernel (9 repos,
|                                    gateway triads, state machines)
|   8. (Future) module providers  — Donations, CMS, Gallery, Events, ...
|
| Future phases must extend this list without reordering existing entries.
*/

return [
    App\Providers\AppServiceProvider::class,
    App\Shared\Providers\SharedServiceProvider::class,
    App\Persistence\Providers\PersistenceServiceProvider::class,
    App\Runtime\Providers\RuntimeServiceProvider::class,
    App\Redis\Providers\RedisServiceProvider::class,
    App\Payments\Providers\PaymentsServiceProvider::class,
];