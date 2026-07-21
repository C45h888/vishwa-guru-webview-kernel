<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\RazorpayCheckoutController;
use App\Http\Controllers\Public\Donate\FormController as DonateFormController;
use App\Http\Controllers\Public\Donate\SubmitController as DonateSubmitController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Donation Flow Routes — Sub-project 3 (Phase 3) GET endpoints
|--------------------------------------------------------------------------
|
| Routes are split across two prefix groups:
|   - /api/v1/* — POST + future async endpoints (api + idempotency middleware)
|   - /* (web)   — GETs that serve Inertia pages (web middleware)
|
| Doctrine: the controller is pure transport. RazorpayCheckoutController
| builds a DonationIntent from validated input and hands it to
| PaymentService::initialize, which delegates to PaymentOrchestrator →
| PaymentProviderSelector → RazorpayAdapter → RazorpayClient → PHP SDK.
| Every Payment state transition happens inside the orchestrator under
| the PaymentStateMachine.
|
| Route names: `donate.form`, `donate.submit`, `payments.razorpay.checkout`.
| URL paths:  `/donate`, `/donate/success`, `/api/v1/razorpay/checkout`.
*/

// ── Public Inertia pages (mounted by RouteServiceProvider under web) ───

Route::get('/donate', DonateFormController::class)->name('donate.form');
Route::get('/donate/success', DonateSubmitController::class)->name('donate.submit');

// ── JSON submission (mounted under /api/v1 via RouteServiceProvider) ──

Route::post(
    '/razorpay/checkout',
    [RazorpayCheckoutController::class, 'store']
)->name('payments.razorpay.checkout');
