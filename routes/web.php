<?php

declare(strict_types=1);

use App\Http\Controllers\Public\CmsPageController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\CmsMedia\ShowController as CmsMediaShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/media/{id}', CmsMediaShowController::class)
    ->where('id', '[A-Za-z0-9]+')
    ->name('cms.public-media.show');

Route::get('/contact', [ContactController::class, '__invoke'])
    ->name('cms.contact');

/*
 * Generic CMS page resolver for /about, /privacy, /terms, /trustee,
 * /mission, /policies. Whitelist regex prevents shadowing
 * /campaigns/{slug}, /gallery/{slug}, /events/{slug}.
 *
 * Adding more public static pages: edit the regex + seed a row in the
 * static_pages table with the matching slug.
 */
Route::get('/{slug}', [CmsPageController::class, 'show'])
    ->where('slug', '(about|privacy|terms|trustee|mission|policies)')
    ->name('cms.public-page.show');