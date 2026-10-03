<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Donate\FormController as DonateFormController;
use App\Http\Controllers\Public\Donate\SubmitController as DonateSubmitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Donation UI Routes — web middleware, /donate and friends
|--------------------------------------------------------------------------
|
| Mounted by RouteServiceProvider under the web middleware group so
| Inertia\Middleware\HandleInertiaRequests runs and the session/cookies
| stack is available. The Razorpay HTTP submission lives in
| routes/donation.php under /api/v1/razorpay/checkout (JSON).
|
| Surface:
|   GET /donate              — donation form (FormController)
|   GET /donate/success      — status polling page (SubmitController)
|   GET /donate/cancel       — Razorpay modal dismissed (Cancel page)
|
| Deep linking (read by FormController):
|   ?campaign={slug}    — preselect campaign
|   ?amount={rupees}    — preselect amount in rupees
|   ?recurring={key}     — recurring flag (UI-only, V1)
|   ?anonymous=1         — preselect anonymous donation
|
| Route names: `donate.form`, `donate.status`, `donate.cancel`.
*/

Route::get('/donate', DonateFormController::class)->name('donate.form');
Route::get('/donate/success', DonateSubmitController::class)->name(
    'donate.status',
);
Route::get('/donate/cancel', function (\App\Seo\Contracts\SeoMetaContract $seo) {
    return \Inertia\Inertia::render('payments/Cancel', [
        'appName' => (string) config('app.name', 'Temple Trust'),
        'appUrl' => (string) config('app.url'),
        'seo' => $seo->forPage(title: 'Donation cancelled', noindex: true),
    ]);
})->name('donate.cancel');