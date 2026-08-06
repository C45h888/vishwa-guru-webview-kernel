<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Events\Contracts\EventAuthoringContract;
use App\Events\Domain\Exceptions\DuplicateEventSlugException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Events\UpdateEventRequest;
use Illuminate\Http\RedirectResponse;

/**
 * UpdateController — handles PUT /admin/events/{event}.
 */
final class UpdateController extends Controller
{
    public function __construct(
        private readonly EventAuthoringContract $authoring,
    ) {
    }

    public function __invoke(UpdateEventRequest $request, string $event): RedirectResponse
    {
        try {
            $updated = $this->authoring->update($event, $request->toInput());
        } catch (DuplicateEventSlugException $e) {
            return redirect()
                ->route('admin.events.edit', ['event' => $event])
                ->withErrors(['slug' => $e->getMessage()])
                ->withInput();
        }

        if ($updated === null) {
            abort(404, 'Event not found.');
        }

        return redirect()
            ->route('admin.events.edit', ['event' => $updated->id])
            ->with('status', 'Event updated.');
    }
}
