<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\RazorpayCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Donation Flow Routes — JSON submission (mounted under /api/v1)
|--------------------------------------------------------------------------
|
| Mounted by RouteServiceProvider with the api + idempotency middleware
| groups and the /api/v1 prefix. The `idempotency` middleware applies
| the SETEX dedupe path to all mutating verbs (POST, PUT, PATCH,
| DELETE). GETs would bypass naturally; we deliberately do not serve
| any GETs from this file.
|
| Doctrine: the controller is pure transport. It builds a DonationIntent
| from validated input, hands it to PaymentService::initialize, which
| delegates to PaymentOrchestrator → PaymentProviderSelector →
| RazorpayAdapter → RazorpayClient → PHP SDK. Every Payment state
| transition happens inside the orchestrator under the PaymentStateMachine.
|
| Route name: `payments.razorpay.checkout`.
| URL path:   `/api/v1/razorpay/checkout`.
*/

Route::post(
    '/razorpay/checkout',
    [RazorpayCheckoutController::class, 'store']
)->name('payments.razorpay.checkout');
