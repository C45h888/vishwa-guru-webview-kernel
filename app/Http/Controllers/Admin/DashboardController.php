<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DashboardController — post-login landing for the canonical admin.
 *
 * Doctrine (AGENTS.md §"Phase 4: Admin Kernel"):
 *   - Thin controller: no business logic, no DB calls. Future Pass 2/3
 *     controllers will populate real authoring stats (pending campaigns,
 *     upcoming events, etc.) — for Pass 1 the dashboard proves the
 *     auth loop works end-to-end.
 *   - The `stats` payload is currently a static placeholder. When Pass 2
 *     (campaigns admin) lands, we replace it with a real read-side
 *     service call. Until then, the shape is stable so the Svelte
 *     component doesn't need to change.
 */
final class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'campaigns_count' => null,   // populated by Pass 2
                'events_count'    => null,   // populated by Pass 3
                'upcoming_events' => [],     // populated by Pass 3
            ],
        ]);
    }
}
