<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public campaigns routes — reserved in Sub-project 1
|--------------------------------------------------------------------------
|
| These routes are reserved in Sub-project 1 (Content Read Surfaces)
| so the URL namespace and route names are locked before Sub-project 3
| (Public UI Skeleton Pages) wires real controllers.
|
| Route names: `campaigns.index`, `campaigns.show`.
| URL paths:  `/campaigns`, `/campaigns/{slug}`.
|
| The closures below return HTTP 501 — controllers arrive in Sub-project 3.
*/

Route::get('/campaigns', function (Request $request) {
    return response()->json(
        ['error' => 'campaigns.index not yet implemented'],
        501,
    );
})->name('campaigns.index');

Route::get('/campaigns/{slug}', function (Request $request, string $slug) {
    return response()->json(
        ['error' => 'campaigns.show not yet implemented', 'slug' => $slug],
        501,
    );
})->name('campaigns.show');
