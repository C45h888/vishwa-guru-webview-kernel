<?php

declare(strict_types=1);

namespace App\Mail\Contracts;

use App\Shared\Support\Result;

/**
 * ReceiptSourceContract — the mail package's inbound data port.
 *
 * "Give me the typed document + donor data + the receipt PDF for receipt
 * X." Implemented on the Payments side (App\Payments\Mail\ReceiptSourceAdapter)
 * which delegates to the PREDEFINED DB workers — the receipts package's
 * DataWorker/TypesWorker via ReceiptSubstrate — so the TypesWorker stays
 * the single type producer in the system, and the mail package never
 * reaches into Payments internals.
 *
 * This port (plus DeliveryBookkeepingContract) is the package's only
 * sanctioned inbound edge. The typed ReceiptDocument it returns is the
 * deliberate shared currency between the two packages — the same
 * snapshot the receipt PDF renders.
 */
interface ReceiptSourceContract
{
    /**
     * @return Result<array{document: \App\Payments\Domain\DTOs\ReceiptDocument, pdf_bytes: string|null}>
     *     pdf_bytes: the HASH-TRUE stored artifact when present (its
     *     SHA-256 is the receipt's content_hash), otherwise null.
     */
    public function bundleFor(\App\Payments\Domain\Entities\Receipt $receipt): Result;
}
