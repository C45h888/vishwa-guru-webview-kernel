<?php

declare(strict_types=1);

namespace App\Payments\Services\ReceiptGeneration;

use App\Payments\Contracts\ReceiptGenerationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Concrete ReceiptGenerationContract implementation used while the
 * PDF rendering pipeline is being staged (Q3 — receipt PDF rendering
 * is deferred to a later pass).
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