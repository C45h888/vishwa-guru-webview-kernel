<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Events\IndexController as EventsIndex;
use App\Http\Controllers\Public\Events\ShowController as EventsShow;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public events routes — wired in Sub-project 3 (Phase 3)
|--------------------------------------------------------------------------
|
| Thin controllers under App\Http\Controllers\Public\Events.
|
| Route names: `events.index`, `events.show`.
| URL paths:  `/events`, `/events/{slug}`.
*/

Route::get('/events', EventsIndex::class)->name('events.index');
Route::get('/events/{slug}', EventsShow::class)->name('events.show');
