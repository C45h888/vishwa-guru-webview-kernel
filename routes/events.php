<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Events\IndexController as EventsIndex;
use App\Http\Controllers\Public\Events\ShowController as EventsShow;
use App\Http\Controllers\Public\Events\JournalIndexController as JournalIndex;
use App\Http\Controllers\Public\Events\JournalShowController as JournalShow;
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
Route::get('/events/journal', JournalIndex::class)->name('events.journal');
Route::get('/events/journal/{slug}', JournalShow::class)->name('events.journal.show');
Route::get('/events/{slug}', EventsShow::class)->name('events.show');
