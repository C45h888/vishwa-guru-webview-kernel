<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Receipt metadata produced by the ReceiptOrchestrator AFTER a payment
 * has been verified and persisted.
 *
 * The physical receipt artifact (PDF) is referenced by fileAssetId; the
 * ReceiptDraft carries the metadata fields needed to create the row in
 * the `receipts` table. The file_assets row is created separately by the
 * ReceiptOrchestrator before persisting the receipts row.
 */
final class ReceiptDraft
{
    public function __construct(
        private readonly Identifier $transactionId,
        private readonly Identifier $donationId,
        private readonly string $receiptNumber,
        private readonly Identifier $fileAssetId,
        private readonly DateTimeImmutable $issuedAt,
        private readonly string $contentHash,
        private readonly ?string $deliveryChannel = null,
        private readonly ?string $deliveryAddress = null,
        private readonly ?string $amountInWords = null,
    ) {
        if (empty($receiptNumber)) {
            throw new InvalidArgumentException('ReceiptDraft receiptNumber cannot be empty');
        }
        // Canonical format produced by ReceiptNumberAllocator:
        //   TR-{4-digit FY year}-{6-digit sequence}-{8-char URL-safe salt}
        // e.g. TR-2026-000001-A7c3ZpQ9. Mirrors the allocator's
        // PATTERN/ANCHORED_PATTERN (routes/receipts.php uses the same) so
        // the draft, the allocator, and the route constraint cannot drift.
        if (! preg_match('/^TR-\d{4}-\d{6}-[A-Za-z0-9_-]{8}$/', $receiptNumber)) {
            throw new InvalidArgumentException(
                "ReceiptDraft receiptNumber must match TR-YYYY-NNNNNN-salt: got {$receiptNumber}"
            );
        }
        if (empty($contentHash)) {
            throw new InvalidArgumentException('ReceiptDraft contentHash cannot be empty');
        }
        if (strlen($contentHash) !== 64 && ! preg_match('/^[a-f0-9]{40,128}$/', $contentHash)) {
            throw new InvalidArgumentException(
                'ReceiptDraft contentHash must be SHA-256 (64 chars hex) or longer hex digest'
            );
        }
        if ($deliveryChannel !== null && ! in_array($deliveryChannel, ['email', 'download', 'postal'], true)) {
            throw new InvalidArgumentException(
                "ReceiptDraft deliveryChannel must be one of email|download|postal; got [{$deliveryChannel}]"
            );
        }
    }

    public function transactionId(): Identifier
    {
        return $this->transactionId;
    }

    public function donationId(): Identifier
    {
        return $this->donationId;
    }

    public function receiptNumber(): string
    {
        return $this->receiptNumber;
    }

    public function fileAssetId(): Identifier
    {
        return $this->fileAssetId;
    }

    public function issuedAt(): DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function contentHash(): string
    {
        return $this->contentHash;
    }

    public function deliveryChannel(): ?string
    {
        return $this->deliveryChannel;
    }

    public function deliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function amountInWords(): ?string
    {
        return $this->amountInWords;
    }

    public function isDelivered(): bool
    {
        return $this->deliveryChannel !== null && $this->deliveryAddress !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId->value(),
            'donation_id' => $this->donationId->value(),
            'receipt_number' => $this->receiptNumber,
            'file_asset_id' => $this->fileAssetId->value(),
            'issued_at' => $this->issuedAt->format(DATE_ATOM),
            'content_hash' => $this->contentHash,
            'delivery_channel' => $this->deliveryChannel,
            'delivery_address' => $this->deliveryAddress,
            'amount_in_words' => $this->amountInWords,
        ];
    }

    /**
     * Factory for building a ReceiptDraft from the renderer pipeline.
     */
    public static function fromRenderer(
        Identifier $transactionId,
        Identifier $donationId,
        string $receiptNumber,
        Identifier $fileAssetId,
        DateTimeImmutable $issuedAt,
        string $contentHash,
        ?string $amountInWords,
        ?string $deliveryChannel = null,
        ?string $deliveryAddress = null,
    ): self {
        return new self(
            transactionId: $transactionId,
            donationId: $donationId,
            receiptNumber: $receiptNumber,
            fileAssetId: $fileAssetId,
            issuedAt: $issuedAt,
            contentHash: $contentHash,
            deliveryChannel: $deliveryChannel,
            deliveryAddress: $deliveryAddress,
            amountInWords: $amountInWords,
        );
    }
}