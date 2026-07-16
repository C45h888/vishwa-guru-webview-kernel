<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Generates receipts for completed payment transactions.
 * Receipts are immutable artifacts produced after a successful
 * gateway-verified payment.
 *
 * Phase 0.25 surface (generate) is preserved unchanged.
 * Pass 1.7 introduces draft() for the richer ReceiptDraft return.
 */
interface ReceiptGenerationContract
{
    /**
     * Phase 0.25 method — UNCHANGED.
     * Returns a flat array for backward compatibility.
     *
     * @return Result<array{receipt_number: string, issued_at: string, download_url: string, content_hash: string}>
     */
    public function generate(Identifier $transactionId): Result;

    /**
     * Pass 1.7 method — returns a rich ReceiptDraft.
     * ReceiptService uses this in Pass 1.7.
     *
     * @return Result<ReceiptDraft>
     */
    public function draft(Identifier $transactionId): Result;

    /**
     * Generate a receipt number for a given transaction.
     * Format: TR-YYYY-{transaction_short_id}
     */
    public function receiptNumber(Identifier $transactionId): string;

    /**
     * Whether receipts are enabled in the current environment.
     */
    public function isEnabled(): bool;
}
