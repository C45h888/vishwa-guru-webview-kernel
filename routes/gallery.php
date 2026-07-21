<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Gallery\IndexController as GalleryIndex;
use App\Http\Controllers\Public\Gallery\ShowController as GalleryShow;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public gallery routes — wired in Sub-project 3 (Phase 3)
|--------------------------------------------------------------------------
|
| Thin controllers under App\Http\Controllers\Public\Gallery.
|
| Route names: `gallery.index`, `gallery.show`.
| URL paths:  `/gallery`, `/gallery/{slug}`.
*/

Route::get('/gallery', GalleryIndex::class)->name('gallery.index');
Route::get('/gallery/{slug}', GalleryShow::class)->name('gallery.show');
