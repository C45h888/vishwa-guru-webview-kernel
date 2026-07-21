<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Donate\ReceiptController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Receipt detail route — Sub-project 3 (Phase 3)
|--------------------------------------------------------------------------
|
| Single page: GET /receipts/{receiptNumber}.
|
| The receiptNumber parameter is constrained to the canonical format
| TR-YYYY-{shortId} via a regex. Reserved in Sub-project 1; the
| public Inertia page lands in Sub-project 3.
*/

Route::get(
    '/receipts/{receiptNumber}',
    ReceiptController::class,
)->where('receiptNumber', 'TR-\d{4}-[A-Z0-9]{4,32}')
    ->name('receipts.show');
