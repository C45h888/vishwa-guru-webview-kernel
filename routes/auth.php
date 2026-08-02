<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Auth Routes — minimal Breeze-equivalent surface
|--------------------------------------------------------------------------
|
| ONLY login + logout are wired. Register, forgot-password, reset-password,
| email-verification, and confirm-password are INTENTIONALLY ABSENT because:
|   - Single canonical admin: registration is not a public surface
|   - Admin credentials are managed via env (ADMIN_EMAIL/ADMIN_PASSWORD)
|     and the AdminSeeder — not via a "forgot password" flow
|   - AGENTS.md "Phase 4 work must not be partially implemented" — we
|     either build these flows (with their tests + UI + rate limits +
|     email integration) or omit them. Pass 1 omits them.
|
| When/if the temple trust ever needs multiple admins, the standard
| Laravel flows land behind their own constitutional section. Until
| then, this file mirrors Breeze 1.x's structure 1:1 so the future
| upgrade is a swap, not a rewrite.
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
