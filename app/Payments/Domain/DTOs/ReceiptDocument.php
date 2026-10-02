<?php

declare(strict_types=1);

namespace App\Payments\Domain\DTOs;

use App\Payments\Domain\Enums\Currency;
use DateTimeImmutable;

/**
 * ReceiptDocument — the canonical typed receipt document.
 *
 * Produced ONLY by the types worker (App\Payments\Receipts\Workers\TypesWorker)
 * from data transported by the data worker. Every receipt surface — the
 * designed PDF, the web receipt page, the email body, Form 10BD export —
 * consumes this one typed shape, so a field can never drift between
 * surfaces again.
 *
 * Typing rules owned here:
 *   - money: integer minor units + canonical display string (Indian
 *     grouping for INR, e.g. ₹1,00,000.00)
 *   - dates: DateTimeImmutable for machine use + one display dialect
 *     ("02 October 2026", Asia/Kolkata) for humans
 *   - 80G: the trust's REGISTRATION number and the per-receipt
 *     CERTIFICATE number are separate fields (never conflated)
 *
 * The document is immutable. It is the single snapshot the design worker
 * renders AND the receipt row is persisted from — document fields and
 * runtime state are the same facts by construction.
 */
final readonly class ReceiptDocument
{
    public function __construct(
        // Identity
        public string $receiptNumber,
        public string $fyLabel,
        public string $campaignTitle,

        // Donor (snapshot as rendered)
        public string $donorName,
        public ?string $donorEmail,
        public ?string $donorPan,
        public string $donorAddressBlock,

        // Money (typed + canonical display)
        public int $amountMinor,
        public Currency $currency,
        public string $amountDisplay,
        public string $amountInWords,

        // Payment provenance
        public string $paymentReference,
        public ?DateTimeImmutable $paymentDate,
        public string $paymentDateDisplay,

        // Issue timing
        public DateTimeImmutable $generatedAt,
        public string $issuedDateDisplay,

        // Tax / 80G block
        public bool $isTaxDeductible,
        public bool $tax80gEligible,
        public ?string $tax80gCertificateNumber,
        public ?string $tax80gRegistrationNumber,
        public ?string $tax80gNote,

        // Donee (trust) block
        public string $trustName,
        public string $trustAddress,
        public string $trustEmail,
        public string $trustPhone,
        public ?string $trustPan,

        // Runtime state echo (populated when a persisted receipt exists)
        public string $contentHash = '',
        public string $state = 'generated',
    ) {
    }

    /**
     * Canonical public-read wire shape for the receipt web page
     * (resources/js/domains/payments/Receipt.svelte via ReceiptProps in
     * resources/js/shared/lib/inertia.ts).
     *
     * PII rule: donor PAN and postal address stay OFF the wire — they
     * appear only on the designed PDF, which is behind the same
     * access-token gate. Everything a donor needs to identify and trust
     * the receipt is here.
     *
     * @return array<string, mixed>
     */
    public function toReadProjection(): array
    {
        return [
            'receipt_number'            => $this->receiptNumber,
            'fy_label'                  => $this->fyLabel,
            'campaign_title_snapshot'   => $this->campaignTitle,
            'donor_name'                => $this->donorName,
            'donor_email'               => $this->donorEmail,
            'amount_minor'              => $this->amountMinor,
            'currency_code'             => $this->currency->value,
            'amount_display'            => $this->amountDisplay,
            'amount_in_words'           => $this->amountInWords,
            'payment_reference'         => $this->paymentReference,
            'payment_date_display'      => $this->paymentDateDisplay,
            'issued_date_display'       => $this->issuedDateDisplay,
            'is_tax_deductible'         => $this->isTaxDeductible,
            'tax_80g_eligible'          => $this->tax80gEligible,
            'tax_80g_certificate_number' => $this->tax80gCertificateNumber,
            'tax_80g_registration_number' => $this->tax80gRegistrationNumber,
            'content_hash'              => $this->contentHash,
            'state'                     => $this->state,
            'generated_at'              => $this->generatedAt->format(DATE_ATOM),
        ];
    }
}
