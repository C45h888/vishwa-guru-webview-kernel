<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Events\Contracts\EventAuthoringContract;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/**
 * EndController — handles POST /admin/events/{event}/end.
 *
 * Doctrine:
 *   - POST (not GET) because this is a state mutation.
 *   - Idempotent: if the event is already 'completed', the service
 *     returns the existing row unchanged. No double-stamping of
 *     completed_at.
 *   - Redirect back to the edit page with a status flash. The Svelte
 *     page renders the confirmation modal BEFORE the POST, so by the
 *     time this endpoint is hit, the admin has already confirmed.
 *   - If the event doesn't exist, 404 (matches the campaign update
 *     path's null-check pattern).
 */
final class EndController extends Controller
{
    public function __construct(
        private readonly EventAuthoringContract $authoring,
    ) {
    }

    public function __invoke(string $event): RedirectResponse
    {
        $updated = $this->authoring->end($event);

        if ($updated === null) {
            abort(404, 'Event not found.');
        }

        return redirect()
            ->route('admin.events.edit', ['event' => $updated->id])
            ->with('status', 'Event marked as completed.');
    }
}
