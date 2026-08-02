<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\CmsPageController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\CmsMedia\ShowController as CmsMediaShowController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/media/{id}', CmsMediaShowController::class)
    ->where('id', '[A-Za-z0-9_]+')
    ->name('cms.public-media.show');

Route::get('/contact', [ContactController::class, '__invoke'])
    ->name('cms.contact');

/*
 * Dedicated /about route — structured About page with values, timeline,
 * trustees, and a CMS-driven donate CTA. Mirrors the Home page pattern.
 * Must be registered BEFORE the generic {slug} fallback below.
 */
Route::get('/about', [AboutController::class, 'index'])
    ->name('cms.about');

/*
 * Generic CMS page resolver for /privacy, /terms, /trustee,
 * /mission, /policies. Whitelist regex prevents shadowing
 * /campaigns/{slug}, /gallery/{slug}, /events/{slug}, /about.
 *
 * Adding more public static pages: edit the regex + seed a row in the
 * static_pages table with the matching slug. /about is NOT in the
 * whitelist because it has its own dedicated route.
 */
Route::get('/{slug}', [CmsPageController::class, 'show'])
    ->where('slug', '(privacy|terms|trustee|mission|policies)')
    ->name('cms.public-page.show');