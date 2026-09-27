<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Events\IndexController as EventsIndex;
use App\Http\Controllers\Public\Events\ShowController as EventsShow;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public events routes
|--------------------------------------------------------------------------
|
| Thin controllers under App\Http\Controllers\Public\Events.
|
| After Pass 7 (events page canonical surface), the events surface is
| fully DB-driven: IndexController reads upcoming + past from the events
| table and renders banner cards via /media/{cms_media_id}. The static
| journal catalog (App\Events\Content\event-articles.php) and its
| /events/journal{,/{slug}} routes were retired in this pass; the file
| itself stays on disk (path-pinned for future admin authoring) but no
| controller reads it.
|
| Route names: `events.index`, `events.show`.
| URL paths:  `/events`, `/events/{slug}`.
*/

Route::get('/events', EventsIndex::class)->name('events.index');
Route::get('/events/{slug}', EventsShow::class)->name('events.show');
