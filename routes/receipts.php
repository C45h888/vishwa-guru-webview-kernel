<?php

declare(strict_types=1);

use App\Http\Controllers\Public\Donate\ReceiptController;
use App\Http\Controllers\Public\Donate\ReceiptDownloadController;
use App\Payments\Infrastructure\Receipts\ReceiptNumberAllocator;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Receipt Routes — Sub-project 3 (Phase 3)
|--------------------------------------------------------------------------
|
| Two GET routes for the canonical receipt surface:
|
|   GET /receipts/{number}            — full Inertia receipt page (cms/Receipt)
|   GET /receipts/{number}/download   — PDF stream via ReceiptPdfGenerator
|
| Both constrained to the canonical receipt format
| TR-{FY_year}-{06_digit_sequence} via ReceiptNumberAllocator::PATTERN.
| The pattern is the single source of truth shared between the allocator
| that *produces* receipt numbers and the routes that *match* them.
| ReceiptNumberPatternTest (tests/Unit/Payments/Infrastructure/Receipts/)
| asserts the two stay in lockstep.
*/

Route::get(
    '/receipts/{receiptNumber}',
    ReceiptController::class,
)->where('receiptNumber', ReceiptNumberAllocator::PATTERN)
    ->name('receipts.show');

Route::get(
    '/receipts/{receiptNumber}/download',
    ReceiptDownloadController::class,
)->where('receiptNumber', ReceiptNumberAllocator::PATTERN)
    ->name('receipts.download');