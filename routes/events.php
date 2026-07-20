<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public events routes — reserved in Sub-project 1
|--------------------------------------------------------------------------
|
| Route names: `events.index`, `events.show`.
| URL paths:  `/events`, `/events/{slug}`.
|
| Controllers arrive in Sub-project 3 (Public UI Skeleton Pages).
*/

Route::get('/events', function (Request $request) {
    return response()->json(
        ['error' => 'events.index not yet implemented'],
        501,
    );
})->name('events.index');

Route::get('/events/{slug}', function (Request $request, string $slug) {
    return response()->json(
        ['error' => 'events.show not yet implemented', 'slug' => $slug],
        501,
    );
})->name('events.show');
