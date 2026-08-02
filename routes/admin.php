<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes — gated behind [web, auth, admin]
|--------------------------------------------------------------------------
|
| Every route in this file requires:
|   - web:     session + CSRF + cookies
|   - auth:    authenticated user
|   - admin:   user->isAdmin() === true  (EnsureUserIsAdmin middleware)
|
| Pass 1 ships only the dashboard. Pass 2 will add /admin/campaigns/*;
| Pass 3 will add /admin/events/*. Both follow the same triple-middleware
| pipeline so the role check is enforced once at the group level.
*/

Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', DashboardController::class)->name('dashboard');
    });
