<?php

declare(strict_types=1);

namespace App\Events;

use App\Shared\Contracts\ModuleContract;

/**
 * Events kernel module declaration.
 *
 * The Events kernel owns the public read surface for temple events
 * (upcoming + past). It exposes `EventsQueryContract` for Phase 3 UI
 * consumption; admin mutations arrive in Phase 4.
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
final class EventsModule implements ModuleContract
{
    public function name(): string
    {
        return 'events';
    }

    /**
     * The Events kernel depends on:
     *   - Shared (every kernel does)
     *
     * V1 is read-only — no Shape A bridges in or out. Sub-project 3
     * (UI) will consume the Events public contract directly via the
     * CMS module's `dependencies()` list.
     *
     * @return list<class-string>
     */
    public function dependencies(): array
    {
        return [
            \App\Shared\Contracts\ModuleContract::class,
        ];
    }
}
