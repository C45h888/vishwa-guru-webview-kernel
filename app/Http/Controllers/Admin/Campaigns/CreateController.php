<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Campaigns;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CreateController — admin "new campaign" form.
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
    private const ALLOWED_STATES = ['draft', 'active', 'completed'];

    public function __invoke(): Response
    {
        return Inertia::render('admin/campaigns/Create', [
            'states' => self::ALLOWED_STATES,
            'defaults' => [
                'state' => 'draft',
                'currency_code' => 'INR',
                'is_featured' => false,
                'display_order' => 0,
            ],
        ]);
    }
}
