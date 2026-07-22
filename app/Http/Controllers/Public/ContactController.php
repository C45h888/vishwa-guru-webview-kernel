<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Cms\Services\ContactInformationService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contact page (GET /contact).
 *
 * Reads primary contact points from ContactInformationService. The page is
 * empty-state safe — when no rows are seeded, the Svelte page renders its
 * own fallback message. Doctrine: controller is pure transport; no business
 * logic, no filtering, no formatting beyond toArray().
 */
final class ContactController
{
    public function __invoke(ContactInformationService $contacts): Response
    {
        $points = array_map(
            static fn ($entity) => $entity->toArray(),
            $contacts->listPrimary(),
        );

        return Inertia::render('cms/Contact', [
            'contactPoints' => $points,
            'appName' => (string) config('app.name', 'Temple Trust'),
            'appUrl' => (string) config('app.url'),
        ]);
    }
}