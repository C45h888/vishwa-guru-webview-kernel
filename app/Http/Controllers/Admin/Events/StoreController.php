<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Events\Contracts\EventAuthoringContract;
use App\Events\Domain\Exceptions\DuplicateEventSlugException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Events\StoreEventRequest;
use Illuminate\Http\RedirectResponse;

/**
 * StoreController — handles POST /admin/events.
 *
 * Doctrine: thin controller. FormRequest validates input and produces
 * the EventDraftInput DTO; the service does the actual insert; the
 * controller translates outcomes to HTTP responses.
 */
final class StoreController extends Controller
{
    public function __construct(
        private readonly EventAuthoringContract $authoring,
    ) {
    }

    public function __invoke(StoreEventRequest $request): RedirectResponse
    {
        try {
            $event = $this->authoring->create($request->toInput());
        } catch (DuplicateEventSlugException $e) {
            return redirect()
                ->route('admin.events.create')
                ->withErrors(['slug' => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('admin.events.edit', ['event' => $event->id])
            ->with('status', 'Event created.');
    }
}
