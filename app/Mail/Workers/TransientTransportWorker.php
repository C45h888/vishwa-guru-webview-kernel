<?php

declare(strict_types=1);

namespace App\Mail\Workers;

use App\Payments\Domain\Entities\Receipt;
use App\Mail\Contracts\ReceiptSourceContract;
use App\Shared\Support\Result;

/**
 * TransientTransportWorker — the primitive transport, converted into a
 * worker boundary. PURE TRANSPORT of data and types: crosses to the
 * predefined DB workers (the receipts package's DataWorker + TypesWorker)
 * through the ReceiptSourceContract port and pulls exactly what the email
 * needs — the typed ReceiptDocument + donor data + the stored receipt PDF
 * bytes. No logic, no formatting, no decisions.
 */
final class TransientTransportWorker
{
    public function __construct(
        private readonly ReceiptSourceContract $source,
    ) {
    }

    /**
     * @return Result<array{document: \App\Payments\Domain\DTOs\ReceiptDocument, pdf_bytes: string|null}>
     */
    public function fetch(Receipt $receipt): Result
    {
        return $this->source->bundleFor($receipt);
    }
}
