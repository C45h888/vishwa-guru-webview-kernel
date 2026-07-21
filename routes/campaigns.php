<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Campaigns\IndexController as CampaignsIndex;
use App\Http\Controllers\Public\Campaigns\ShowController as CampaignsShow;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public campaigns routes — wired in Sub-project 3 (Phase 3)
|--------------------------------------------------------------------------
|
| Thin controllers under App\Http\Controllers\Public\Campaigns.
| Both routes mounted by RouteServiceProvider under the web middleware
| group with Inertia\Middleware\HandleInertiaRequests active.
|
| Route names: `campaigns.index`, `campaigns.show`.
| URL paths:  `/campaigns`, `/campaigns/{slug}`.
*/

Route::get('/campaigns', CampaignsIndex::class)->name('campaigns.index');
Route::get('/campaigns/{slug}', CampaignsShow::class)->name('campaigns.show');
