<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\RazorpayCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Donation Flow Routes — Phase 3 Razorpay Standard Checkout
|--------------------------------------------------------------------------
|
| The `idempotency` middleware applies the SETEX dedupe path to all
| mutating verbs (POST, PUT, PATCH, DELETE). GETs bypass the middleware.
| Middleware is declared at the route group level, not inside controllers
| (architectural invariant: controllers stay thin).
|
| Doctrine: the controller is pure transport. It builds a DonationIntent
| from validated input, hands it to PaymentService::initialize, which
| delegates to PaymentOrchestrator → PaymentProviderSelector →
| RazorpayAdapter → RazorpayClient → PHP SDK. Every Payment state
| transition happens inside the orchestrator under the PaymentStateMachine.
|
| Route is prefixed /api/v1 by RouteServiceProvider.
*/

Route::post(
    '/razorpay/checkout',
    [RazorpayCheckoutController::class, 'store']
)->name('payments.razorpay.checkout');
