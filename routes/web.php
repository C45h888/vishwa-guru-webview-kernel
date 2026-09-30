<?php

declare(strict_types=1);

use App\Http\Controllers\Public\AboutController;
use App\Http\Controllers\Public\CmsPageController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\LegalController;
use App\Http\Controllers\Public\Legal\DocumentController as LegalDocumentController;
use App\Http\Controllers\Public\CmsMedia\ShowController as CmsMediaShowController;
use App\Http\Controllers\Public\Seo\RobotsController as SeoRobotsController;
use App\Http\Controllers\Public\Seo\SitemapController as SeoSitemapController;
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
 * Dedicated /legal route — displays the trust's legal registration,
 * tax-exempt status (80G, 12A), POA, and TAN certificates.
 * Mirrors the /about pattern: dedicated route, dedicated controller,
 * dedicated Svelte component. Structured content is resolved from the
 * legal_page_content JSONB column via LegalPageContentFactory.
 * Must be registered BEFORE the generic {slug} fallback below.
 */
Route::get('/legal', [LegalController::class, 'index'])
    ->name('cms.legal');

/*
 * Legal-document streaming route — serves the trust's compliance PDFs
 * (80G, 12A, POA, TAN) from the private local disk. The {key} param
 * is whitelisted inside DocumentController against
 * LegalCertificate::ALLOWED_KEYS so unknown values 404.
 *
 * Registered BEFORE the generic {slug} fallback so /legal/documents/*
 * never falls through to the CmsPageController. Also constrained to
 * the four canonical keys at the route level for early rejection.
 */
Route::get('/legal/documents/{key}', LegalDocumentController::class)
    ->where('key', '(eighty_g|twelve_a|poa|tan)')
    ->name('cms.legal.document');

/*
 * SEO surface — /robots.txt + /sitemap.xml. Registered BEFORE the
 * generic {slug} fallback below so the literal paths are never
 * captured by CmsPageController (same doctrine as /about and /legal
 * above). Controllers return raw text/XML, not Inertia responses.
 */
Route::get('/robots.txt', SeoRobotsController::class)->name('seo.robots');
Route::get('/sitemap.xml', SeoSitemapController::class)->name('seo.sitemap');

/*
 * Generic CMS page resolver for /privacy, /terms, /trustee,
 * /mission, /policies. Whitelist regex prevents shadowing
 * /campaigns/{slug}, /gallery/{slug}, /events/{slug}, /about, /legal.
 *
 * Adding more public static pages: edit the regex + seed a row in the
 * static_pages table with the matching slug. /about and /legal are NOT
 * in the whitelist because they have their own dedicated routes.
 */
Route::get('/{slug}', [CmsPageController::class, 'show'])
    ->where('slug', '(privacy|terms|trustee|mission|policies)')
    ->name('cms.public-page.show');