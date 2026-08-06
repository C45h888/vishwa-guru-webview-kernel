<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Events\Domain\Repositories\EventRepositoryContract;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * IndexController — admin events list page.
 *
 * Doctrine: lists ALL events (state IN 'draft','published','completed'),
 * sorted by starts_at DESC. Public surface still filters to
 * displayable; the admin sees everything.
 */
final class IndexController extends Controller
{
    public function __construct(
        private readonly EventRepositoryContract $repository,
    ) {
    }

    public function __invoke(): Response
    {
        $page = (int) request()->query('page', 1);
        if ($page < 1) {
            $page = 1;
        }
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $result = $this->repository->listAllIncludingDrafts($perPage, $offset);
        $items = array_map(
            static fn ($dto) => $dto->toArray(),
            $result['items'],
        );

        return Inertia::render('admin/events/Index', [
            'events' => $items,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'has_more' => ($page * $perPage) < $result['total'],
            ],
        ]);
    }
}
