<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Donation Flow Routes — Phase 2 HTTP Idempotency-Key Sample
|--------------------------------------------------------------------------
|
| Sample route file for the donation flow. The `idempotency` middleware
| applies the SETEX dedupe path to all mutating verbs (POST, PUT, PATCH,
| DELETE). GETs bypass the middleware naturally.
|
| Phase 3 (public site) replaces the closure-based handler here with
| the real DonationController. The route paths and middleware group
| stay stable; only the handler body changes.
|
| Doctrine: middleware at the route group, not inside controllers.
| Controllers stay thin (architecture.md line 99-104).
|
*/

Route::post('/donate', function () {
    return response()->json(
        ['error' => 'Not Implemented — Phase 3 donation endpoints land here.'],
        501,
    );
})->name('donation.create');
