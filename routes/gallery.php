<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public gallery routes — reserved in Sub-project 1
|--------------------------------------------------------------------------
|
| Route names: `gallery.index`, `gallery.show`.
| URL paths:  `/gallery`, `/gallery/{slug}`.
|
| Controllers arrive in Sub-project 3 (Public UI Skeleton Pages).
*/

Route::get('/gallery', function (Request $request) {
    return response()->json(
        ['error' => 'gallery.index not yet implemented'],
        501,
    );
})->name('gallery.index');

Route::get('/gallery/{slug}', function (Request $request, string $slug) {
    return response()->json(
        ['error' => 'gallery.show not yet implemented', 'slug' => $slug],
        501,
    );
})->name('gallery.show');
