<?php

declare(strict_types=1);

use App\Http\Controllers\Payments\RazorpayWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Webhook Routes — Phase 2 SETEX dedupe sample
|--------------------------------------------------------------------------
|
| Inbound Razorpay + PayPal webhook routes. The `webhook-dedupe` middleware
| applies the SETEX dedupe path BEFORE signature verification. Doctrine:
| cheaper check first; expensive HMAC second.
|
| The closures return 501 — Phase 3 (public site) replaces them with the
| real RazorpayWebhookController + PayPalWebhookController that call
| PaymentVerificationService::verify(). The middleware stays stable.
|
*/

Route::post(
    '/razorpay',
    [RazorpayWebhookController::class, 'handle']
)->name('webhook.razorpay');

Route::post('/paypal', function () {
    return response()->json(
        ['error' => 'Not Implemented — Phase 3 webhook controllers land here.'],
        501,
    );
})->name('webhook.paypal');
