<?php

declare(strict_types=1);

namespace App\Payments\Services\ReceiptGeneration;

use App\Payments\Contracts\ReceiptGenerationContract;
use App\Payments\Domain\ValueObjects\ReceiptDraft;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Concrete ReceiptGenerationContract implementation used while the
 * PDF rendering pipeline is being staged.
 *
 * The generator DOES produce:
 *   - a valid receipt_number matching the schema constraint TR-YYYY-{shortId}
 *   - a deterministic content_hash over the canonical receipt payload
 *   - a deterministic, addressable download_url for the future PDF
 *     renderer to populate
 *
 * The generator does NOT produce:
 *   - a real PDF artifact (Pass 1.4 adds file_assets; PDF render is
 *     deferred to Q3)
 *
 * isEnabled() returns true in every environment so the receipt flow
 * can be exercised end-to-end without toggling configuration.
 */
final class StubReceiptGenerator implements ReceiptGenerationContract
{
    public function __construct(
        private readonly Clock $clock,
        private readonly EnvironmentContract $environment,
    ) {}

    /**
     * Phase 0.25 surface — UNCHANGED.
     *
     * @return Result<array{receipt_number: string, issued_at: string, download_url: string, content_hash: string}>
     */
    public function generate(Identifier $transactionId): Result
    {
        $issuedAt = $this->clock->now();
        $receiptNumber = $this->receiptNumber($transactionId);
        $downloadUrl = sprintf(
            'https://receipts.temple-trust.invalid/%s/%s.pdf',
            $issuedAt->format('Y'),
            $receiptNumber,
        );

        $contentHash = $this->hash([
            'transaction_id' => $transactionId->value(),
            'receipt_number' => $receiptNumber,
            'issued_at' => $issuedAt->format(DATE_ATOM),
        ]);

        return Result::success([
            'receipt_number' => $receiptNumber,
            'issued_at' => $issuedAt->format(DATE_ATOM),
            'download_url' => $downloadUrl,
            'content_hash' => $contentHash,
        ]);
    }

    /**
     * Pass 1.7 surface — stub draft for testing.
     *
     * @return Result<ReceiptDraft>
     */
    public function draft(Identifier $transactionId): Result
    {
        $issuedAt = $this->clock->now();
        $receiptNumber = $this->receiptNumber($transactionId);

        $contentHash = $this->hash([
            'transaction_id' => $transactionId->value(),
            'receipt_number' => $receiptNumber,
            'issued_at' => $issuedAt->format(DATE_ATOM),
        ]);

        // Stub fileAssetId — not a real file_assets row in this pass
        $fileAssetId = EntityId::generate('file_asset');

        $draft = ReceiptDraft::fromRenderer(
            transactionId: $transactionId,
            donationId: new Identifier('donor_stub_' . $transactionId->value()),
            receiptNumber: $receiptNumber,
            fileAssetId: new Identifier($fileAssetId->value()),
            issuedAt: $issuedAt,
            contentHash: $contentHash,
            amountInWords: null,
            deliveryChannel: null,
            deliveryAddress: null,
        );

        return Result::success($draft);
    }

    public function receiptNumber(Identifier $transactionId): string
    {
        $year = $this->clock->now()->format('Y');
        $short = strtoupper(substr($transactionId->value(), -12));

        return sprintf('TR-%s-%s', $year, $short);
    }

    public function isEnabled(): bool
    {
        return ! $this->environment->isProduction();
    }

    /**
     * @param  array<string, string>  $parts
     */
    private function hash(array $parts): string
    {
        ksort($parts);
        $canonical = http_build_query($parts);

        return hash('sha256', $canonical);
    }
}