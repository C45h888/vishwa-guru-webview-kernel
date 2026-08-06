<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Events;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CreateController — admin "new event" form.
 *
 * Doctrine: thin controller. The form is rendered with empty values;
 * the Svelte page owns the form state. The state list is passed in
 * so the form's <select> can validate against the canonical set.
 */
final class CreateController extends Controller
{
    /**
     * @var list<string>
     */
    private const ALLOWED_STATES = ['draft', 'published', 'completed'];

    public function __invoke(): Response
    {
        return Inertia::render('admin/events/Create', [
            'states' => self::ALLOWED_STATES,
            'defaults' => [
                'state' => 'draft',
                'timezone' => 'Asia/Kolkata',
                'is_featured' => false,
                'display_order' => 0,
            ],
        ]);
    }
}
