<?php

/*
|--------------------------------------------------------------------------
| Temple Trust Management System — Canonical Service Provider List
|--------------------------------------------------------------------------
|
| THIS FILE IS THE CANONICAL SOURCE OF TRUTH FOR APP SERVICE PROVIDERS.
|
| config/app.php's 'providers' key merges this array on top of
| ServiceProvider::defaultProviders(). Laravel 10.50 reads the merged
| list via Foundation\Application::registerConfiguredProviders() during
| the kernel's RegisterProviders bootstrap step.
|
| Doctrine:
|   - Add a provider here; do not edit config/app.php's provider list.
|   - Order is significant. Boot-order invariants:
|       Shared   → Persistence  (Persistence contracts must resolve first)
|       Persistence → Runtime   (Runtime tags DatabaseHealthProbe on
|                                   PersistenceAdapterContract)
|       Runtime  → Redis        (Redis connector is wired by Runtime-side
|                                   commands before the worker boots)
|       Redis    → Queue        (Queue lives on Redis; both share a Redis
|                                   bus and Queue depends on Redis's
|                                   connection being resolvable)
|       Queue    → Payments     (PaymentsServicesProvider may dispatch
|                                   financial jobs; tries=1)
|       Payments → Cms          (Cross-kernel dependency: the Payments-side
|                                   CampaignQuery adapter resolves via Cms
|                                   modules during boot)
|       Cms      → Campaigns    (Public content read surface precedes the
|                                   Payments-facing Campaigns read surface;
|                                   both share reference-resolution plumbing)
|       Campaigns → Gallery     (independent kernels, ordered for symmetry)
|       Gallery   → Events      (independent kernels, ordered for symmetry)
|
| Future phases must extend this list without reordering existing entries.
| When the Laravel framework is upgraded to 11.x and bootstrap/app.php
| is migrated to Application::configure()->withProviders(...), this file
| is the array passed to withProviders(). No data change required.
*/

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RouteServiceProvider::class,
    App\Shared\Providers\SharedServiceProvider::class,
    App\Persistence\Providers\PersistenceServiceProvider::class,
    App\Runtime\Providers\RuntimeServiceProvider::class,
    App\Redis\Providers\RedisServiceProvider::class,
    App\Queue\Providers\QueueServiceProvider::class,
    App\Payments\Providers\PaymentsServiceProvider::class,
    App\Mail\Providers\MailServiceProvider::class,
    App\Cms\Providers\CmsServiceProvider::class,
    App\Campaigns\Providers\CampaignsServiceProvider::class,
    App\Gallery\Providers\GalleryServiceProvider::class,
    App\Events\Providers\EventsServiceProvider::class,
];