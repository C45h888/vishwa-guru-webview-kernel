<?php

declare(strict_types=1);

use App\Runtime\Http\Controllers\PingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Runtime Routes — Phase 2 Platform Foundation
|--------------------------------------------------------------------------
|
| Loaded by RouteServiceProvider under prefix `api/v1` and the api
| middleware group (ThrottleRequests + SubstituteBindings).
|
| These routes are part of the runtime kernel, NOT the public site.
| Public-site routes (Home, About, Donate, Contact, etc.) land in
| Phase 3 under their own route files.
*/

Route::get('/ping', PingController::class)->name('runtime.ping');