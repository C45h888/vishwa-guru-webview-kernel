<?php

declare(strict_types=1);

namespace App\Payments\Mail;

use App\Mail\Contracts\ReceiptSourceContract;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper;
use App\Payments\Receipts\ReceiptSubstrate;
use App\Shared\Support\Result;

/**
 * ReceiptSourceAdapter — the Payments-side implementation of the mail
 * package's ReceiptSourceContract.
 *
 * Delegates to the PREDEFINED DB workers (the receipts package's
 * DataWorker + TypesWorker through ReceiptSubstrate::documentFor) so the
 * TypesWorker remains the single type producer in the system, and pairs
 * the typed document with the HASH-TRUE stored PDF artifact (its SHA-256
 * is the receipt's content_hash). If the stored artifact is missing the
 * adapter falls back to re-rendering through the design worker.
 */
final class ReceiptSourceAdapter implements ReceiptSourceContract
{
    public function __construct(
        private readonly ReceiptSubstrate $receiptSubstrate,
        private readonly FileAssetRepositoryContract $fileAssets,
        private readonly PdfWrapper $pdf,
    ) {
    }

    public function bundleFor(Receipt $receipt): Result
    {
        $document = $this->receiptSubstrate->documentFor($receipt);

        return Result::success([
            'document' => $document,
            'pdf_bytes' => $this->pdfBytes($receipt),
        ]);
    }

    private function pdfBytes(Receipt $receipt): ?string
    {
        $fileId = $receipt->receiptFileId();
        if ($fileId !== null) {
            $asset = $this->fileAssets->findById($fileId->value());
            if ($asset !== null) {
                $bytes = $this->pdf->read($asset->storageDisk(), $asset->storagePath());
                if ($bytes !== null && $bytes !== '') {
                    return $bytes;
                }
            }
        }

        // Fallback: re-render through the canonical design path.
        $rendered = $this->receiptSubstrate->renderPdfFor($receipt);
        if ($rendered->isFailure()) {
            return null;
        }

        /** @var string $bytes */
        $bytes = $rendered->value();

        return $bytes !== '' ? $bytes : null;
    }
}
