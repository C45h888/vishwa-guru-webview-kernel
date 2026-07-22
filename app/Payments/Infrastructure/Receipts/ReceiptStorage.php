<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Repositories\FileAssetRepositoryContract;
use App\Payments\Domain\Repositories\PaymentDocumentRepositoryContract;
use App\Payments\Domain\ValueObjects\FileAssetRecord;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use RuntimeException;

/**
 * Persists rendered PDF bytes to storage and creates a FileAssetRecord.
 *
 * Returns Result<FileAssetRecord> so the caller (ReceiptRenderer) can
 * compose the ReceiptDraft without depending on Storage::disk directly.
 */
final class ReceiptStorage
{
    public function __construct(
        private readonly \App\Payments\Infrastructure\Receipts\Pdf\PdfWrapper $pdf,
        private readonly Clock $clock,
        private readonly FileAssetRepositoryContract $fileAssets,
        private readonly ConfigurationContract $config,
        private readonly ?PaymentDocumentRepositoryContract $paymentDocuments = null,
    ) {}

    /**
     * Persist rendered PDF bytes as a file_asset row and write to disk.
     *
     * @param  string  $transactionId    ULID of the payment
     * @param  string  $ownerId           ULID of the owning entity (receipt's ULID)
     * @param  string  $pdfBytes          Raw PDF bytes
     * @param  string  $purpose          Purpose tag (use constants below)
     *
     * @return Result<FileAssetRecord>
     */
    public function persist(
        string $transactionId,
        string $ownerId,
        string $pdfBytes,
        string $purpose,
    ): Result {
        try {
            $disk = $this->storageDisk();
            $path = $this->storagePath($transactionId, $ownerId);
            $hash = $this->computeHash($pdfBytes);

            // Write bytes to disk
            $this->pdf->writeToDisk($pdfBytes, $disk, $path);

            // Check for duplicate by hash
            $existing = $this->fileAssets->findByHash($hash);
            if ($existing !== null) {
                // Already stored — reuse the existing record
                $this->paymentDocuments?->ensureReceiptDocument($existing->id());
                return Result::success($existing);
            }

            // Create and persist the file asset record
            $record = FileAssetRecord::create(
                id: $this->generateId(),
                ownerType: 'receipt',
                ownerId: $ownerId,
                originalFilename: "receipt_{$transactionId}.pdf",
                storageDisk: $disk,
                storagePath: $path,
                mimeType: 'application/pdf',
                fileSizeBytes: strlen($pdfBytes),
                fileHashSha256: $hash,
                purpose: $purpose,
                isPublic: false,
            );

            $this->fileAssets->save($record);
            $this->paymentDocuments?->ensureReceiptDocument($record->id());

            return Result::success($record);
        } catch (RuntimeException $e) {
            // @phpstan-ignore-next-line Result<T> generic narrowing limit
            return Result::failure("ReceiptStorage: failed to persist PDF — {$e->getMessage()}");
        } catch (\Throwable $e) {
            // @phpstan-ignore-next-line Result<T> generic narrowing limit
            return Result::failure("ReceiptStorage: unexpected error — {$e->getMessage()}");
        }
    }

    public function storageDisk(): string
    {
        return (string) $this->config->get('receipts.storage_disk', 'local');
    }

    /**
     * Build the storage path for a receipt PDF.
     * Uses the path template from config: receipts/{year}/{receipt_number}.pdf
     */
    public function storagePath(string $transactionId, string $receiptNumber): string
    {
        $template = (string) $this->config->get(
            'receipts.storage_path_template',
            'receipts/{year}/{receipt_number}.pdf',
        );

        $year = $this->clock->now()->format('Y');

        $path = str_replace('{year}', $year, $template);
        $path = str_replace('{receipt_number}', $receiptNumber, $path);
        $path = str_replace('{transaction_id}', $transactionId, $path);

        return $path;
    }

    /**
     * Compute SHA-256 hash of PDF bytes.
     */
    public function computeHash(string $pdfBytes): string
    {
        return hash('sha256', $pdfBytes);
    }

    /**
     * Generate a ULID for the file asset record.
     */
    private function generateId(): string
    {
        // Uses the ULID pattern consistent with EntityId generation.
        // The actual implementation would come from a ULID generator.
        // For now, generate a time-sortable ID:
        return sprintf(
            'fa_%s%s',
            $this->clock->now()->format('ymd'),
            substr(bin2hex(random_bytes(8)), 0, 12),
        );
    }
}
