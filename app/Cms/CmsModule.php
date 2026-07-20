<?php

declare(strict_types=1);

namespace App\Cms;

use App\Shared\Contracts\ModuleContract;

/**
 * CMS kernel module declaration.
 *
 * The CMS kernel is the public-content orchestrator of the Temple Trust
 * Management System. It owns Static Pages, Hero Banners, Static Page
 * References, and Contact Information. The frontend (Phase 3) consumes
 * only this module's read-side surface; cross-kernel data (Campaigns
 * today, Gallery + Events in future passes) is resolved through the
 * Module's declared dependencies, never by importing other kernels'
 * implementation code.
 *
 * Module dependencies (per cms-architecture.md §7):
 *   - Shared (architectural kernel; every module declares this)
 *   - Payments\Contracts\CampaignQueryContract (Shape A kernel bridge;
 *     the producing kernel owns the contract)
 *
 * Doctrine (AGENTS.md + Phase 0.25):
 *   - One binding per interface; kernel-level wiring lives in the
 *     kernel provider, not in domain providers.
 *   - Modules communicate exclusively through service contracts.
 *   - No business logic in this file — the module is a wiring + discovery
 *     artifact, not a service.
 *
 * @see /Users/kamii/Vishwaguru-webview-kernel/vishwa-guru-webview-kernel/cms-architecture.md §7
 */
final class CmsModule implements ModuleContract
{
    public function name(): string
    {
        return 'cms';
    }

    /**
     * The CMS kernel depends on:
     *   - Shared (every kernel does)
     *   - Payments\Contracts\CampaignQueryContract — the Shape A bridge
     *     for cross-kernel campaign reads.
     *
     * The CMS kernel does NOT depend on Payments\Services,
     * Payments\Infrastructure, or Payments\Domain directly. The contract
     * surface is the only sanctioned cross-kernel import.
     *
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            \App\Shared\Contracts\ModuleContract::class,
            \App\Payments\Contracts\CampaignQueryContract::class,
        ];
    }

    /**
     * Boot the module. Called once during application bootstrap, after
     * every Service Provider has registered its bindings.
     *
     * The CMS kernel does no work at boot time. CmsServiceProvider's
     * boot() method handles wiring (RepositoryRegistry entries, cache
     * invalidation listener registration). This method exists to satisfy
     * the ModuleContract surface and to give Shared's discovery a
     * deterministic hook for module-level bootstrap.
     */
    public function boot(): void
    {
        // No-op. CmsServiceProvider::boot() handles wiring.
    }
}