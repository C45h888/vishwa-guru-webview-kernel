<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

/**
 * Controller — Laravel canonical base controller.
 *
 * Mirrors the base class Laravel 10 ships in the AppServiceProvider's
 * skeleton (`Illuminate\Routing\Controller` + the three auth trait imports).
 * Breeze's `AuthenticatedSessionController extends Controller` expects
 * this exact shape, so we recreate it here rather than relying on a
 * downstream installer.
 *
 * Doctrine: a thin base; business logic lives in services, not controllers.
 */
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use ValidatesRequests;
}
