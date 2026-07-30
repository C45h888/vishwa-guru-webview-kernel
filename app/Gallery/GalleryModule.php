<?php

declare(strict_types=1);

namespace App\Gallery;

use App\Shared\Contracts\ModuleContract;

/**
 * Gallery kernel module declaration.
 *
 * The Gallery kernel owns the public read surface for photo galleries
 * (galleries + gallery_images tables). It exposes `GalleryQueryContract`
 * for Phase 3 UI consumption; admin mutations arrive in Phase 4.
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
final class GalleryModule implements ModuleContract
{
    public function name(): string
    {
        return 'gallery';
    }

    /**
     * The Gallery kernel depends on:
     *   - Shared (every kernel does)
     *
     * V1 is read-only — no Shape A bridges in or out. Sub-project 3
     * (UI) will consume the Gallery public contract directly via the
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
