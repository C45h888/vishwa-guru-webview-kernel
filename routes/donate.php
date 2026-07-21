<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Donate\FormController as DonateFormController;
use App\Http\Controllers\Public\Donate\SubmitController as DonateSubmitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Donation UI Routes — web middleware, /donate and /donate/success
|--------------------------------------------------------------------------
|
| Mounted by RouteServiceProvider under the web middleware group (so
| Inertia\Middleware\HandleInertiaRequests runs and the session/cookies
| stack is available). The actual Razorpay HTTP submission lives in
| routes/donation.php under /api/v1/razorpay/checkout; this file only
| carries the GET pages that wrap the result.
|
| Route names: `donate.form`, `donate.submit`.
| URL paths:  `/donate`, `/donate/success`.
*/

Route::get('/donate', DonateFormController::class)->name('donate.form');
Route::get('/donate/success', DonateSubmitController::class)->name('donate.submit');
