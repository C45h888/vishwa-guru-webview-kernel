<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Events\Domain\Repositories\EventRepositoryContract;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * EditController — admin "edit event" form (preloaded with row data).
 */
final class EditController extends Controller
{
    /**
     * @var list<string>
     */
    private const ALLOWED_STATES = ['draft', 'published', 'completed'];

    public function __construct(
        private readonly EventRepositoryContract $repository,
    ) {
    }

    public function __invoke(string $event): Response
    {
        $dto = $this->repository->findByIdIncludingDrafts($event);

        if ($dto === null) {
            abort(404, 'Event not found.');
        }

        return Inertia::render('admin/events/Edit', [
            'event' => $dto->toArray(),
            'states' => self::ALLOWED_STATES,
        ]);
    }
}
