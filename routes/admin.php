<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\Campaigns\CreateController as CampaignsCreate;
use App\Http\Controllers\Admin\Campaigns\EditController as CampaignsEdit;
use App\Http\Controllers\Admin\Campaigns\IndexController as CampaignsIndex;
use App\Http\Controllers\Admin\Campaigns\StoreController as CampaignsStore;
use App\Http\Controllers\Admin\Campaigns\UpdateController as CampaignsUpdate;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Events\CreateController as EventsCreate;
use App\Http\Controllers\Admin\Events\EditController as EventsEdit;
use App\Http\Controllers\Admin\Events\EndController as EventsEnd;
use App\Http\Controllers\Admin\Events\IndexController as EventsIndex;
use App\Http\Controllers\Admin\Events\StoreController as EventsStore;
use App\Http\Controllers\Admin\Events\UpdateController as EventsUpdate;
use App\Http\Controllers\Admin\Media\UploadController as MediaUpload;
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
| Pass 1 ships the dashboard.
| Pass 2 adds /admin/campaigns/* (CRUD + cover image upload).
| Pass 3 adds /admin/events/* (CRUD + end-to-past transition + banner upload).
|
| Doctrine:
|   - All admin routes are Inertia::render — no Blade admin views.
|   - Routes that mutate use POST + redirect (PRG pattern) so refresh
|     doesn't double-submit. Image upload is the exception (JSON
|     response, no redirect).
*/

Route::middleware(['web', 'auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        // Phase 4 Pass 1
        Route::get('/', DashboardController::class)->name('dashboard');

        // Phase 4 Pass 2 — campaigns admin
        Route::get('/campaigns', CampaignsIndex::class)->name('campaigns.index');
        Route::get('/campaigns/new', CampaignsCreate::class)->name('campaigns.create');
        Route::post('/campaigns', CampaignsStore::class)->name('campaigns.store');
        Route::get('/campaigns/{campaign}/edit', CampaignsEdit::class)->name('campaigns.edit');
        Route::put('/campaigns/{campaign}', CampaignsUpdate::class)->name('campaigns.update');

        // Phase 4 Pass 3 — events admin
        Route::get('/events', EventsIndex::class)->name('events.index');
        Route::get('/events/new', EventsCreate::class)->name('events.create');
        Route::post('/events', EventsStore::class)->name('events.store');
        Route::get('/events/{event}/edit', EventsEdit::class)->name('events.edit');
        Route::put('/events/{event}', EventsUpdate::class)->name('events.update');
        // End-event action: state → completed, completed_at = now. Idempotent.
        Route::post('/events/{event}/end', EventsEnd::class)->name('events.end');

        // Phase 4 Pass 2 — media upload (JSON, no redirect).
        // Shared by the campaign + event cover-image uploaders.
        Route::post('/media/upload', MediaUpload::class)->name('media.upload');
    });
