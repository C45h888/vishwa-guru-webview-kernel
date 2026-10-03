<?php

declare(strict_types=1);

namespace App\Payments\Receipts\Workers;

use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Domain\ValueObjects\TrustIdentity;
use App\Payments\Receipts\ReceiptSubstrate;

/**
 * TypesWorker — turns transported data into the typed ReceiptDocument.
 *
 * Semantic boundary: everything about TYPING lives here (money, dates,
 * FY, 80G block, address block, donee identity), and every rule is
 * imported from the parent substrate (ReceiptSubstrate) — the substrate
 * is the single owner of generation logic; workers only move it across
 * boundaries.
 *
 * Donee doctrine:
 *   - PRESENTATION fields (name/address/email/phone) are DB-first with a
 *     config fallback via the substrate (unseeded test DBs).
 *   - CREDENTIAL fields (PAN, TAN, 80G + 12A numbers) are DB-ONLY. They
 *     resolve exclusively from $data['trust_identity'] (transported by
 *     DataWorker from the DB plane). There is deliberately no config/env
 *     fallback — a missing row yields null, never a fabricated value.
 *
 * The 80G determination is the substrate's verify80G(); the transported
 * trust row is passed in so the registration number is DB-sourced too.
 */
final class TypesWorker
{
    public function __construct(
        private readonly ReceiptSubstrate $substrate,
    ) {
    }

    /**
     * Build the document for a receipt being issued (no row yet).
     *
     * @param  array<string, mixed>  $data  DataWorker bundle
     */
    public function build(array $data, string $receiptNumber): ReceiptDocument
    {
        $payment = $data['payment'] ?? null;
        $donation = $data['donation'] ?? null;
        $campaign = $data['campaign'] ?? null;
        $trust = ($data['trust_identity'] ?? null) instanceof TrustIdentity
            ? $data['trust_identity']
            : null;

        [$campaignTitle, $campaignDescription] = $this->campaignBreakdown($campaign);

        $amountMinor = $payment !== null ? $payment->amountMinor() : 0;
        $currency = $payment !== null ? $payment->currency() : \App\Payments\Domain\Enums\Currency::INR;
        $paymentDate = $payment !== null ? ($payment->capturedAt() ?? $payment->createdAt()) : null;

        $donorPan = $donation?->donorPanSnapshot();
        $certificate = $this->substrate->verify80G(
            pan: $donorPan,
            amountMinor: $amountMinor,
            currency: $currency,
            paymentDate: $paymentDate ?? $this->substrate->now(),
            trust: $trust,
        );

        return new ReceiptDocument(
            receiptNumber: $receiptNumber,
            fyLabel: $this->substrate->formatFY($paymentDate ?? $this->substrate->now()),
            campaignTitle: $campaignTitle,
            campaignDescription: $campaignDescription,
            donorName: $donation?->donorNameSnapshot() ?? 'Anonymous',
            donorEmail: $donation?->donorEmailSnapshot(),
            donorPan: $donorPan,
            donorAddressBlock: $this->substrate->formatAddressBlock($donation?->donorAddressSnapshot()),
            amountMinor: $amountMinor,
            currency: $currency,
            amountDisplay: $this->substrate->formatMoney($amountMinor, $currency),
            amountInWords: $this->substrate->amountInWords($amountMinor),
            paymentReference: $payment !== null ? $payment->id()->value() : '',
            paymentDate: $paymentDate,
            paymentDateDisplay: $paymentDate !== null ? $this->substrate->formatDate($paymentDate) : '',
            generatedAt: $this->substrate->now(),
            issuedDateDisplay: $this->substrate->formatDate($this->substrate->now()),
            isTaxDeductible: true,
            tax80gEligible: (bool) $certificate['eligible'],
            tax80gCertificateNumber: $certificate['certificate_number'],
            tax80gRegistrationNumber: $certificate['trust_registration_number'],
            tax80gNote: $certificate['note'],
            trustName: $this->presentational($trust?->name, fn () => $this->substrate->trustName(), 'Temple Trust'),
            trustAddress: $this->presentational($trust?->address, fn () => $this->substrate->trustAddress(), ''),
            trustEmail: $this->presentational($trust?->email, fn () => $this->substrate->trustEmail(), ''),
            trustPhone: $this->presentational($trust?->phone, fn () => $this->substrate->trustPhone(), ''),
            trustPan: $trust?->pan,
            trustTan: $trust?->tan,
            trustTwelveANumber: $trust?->twelveANumber,
        );
    }

    /**
     * Build the document for an ALREADY-PERSISTED receipt (web page,
     * email, re-render). Snapshot fields come from the receipt row — the
     * row is the truth — while payment provenance comes from the
     * transported payment. Donee credentials are re-resolved DB-only (the
     * trust row can gain PAN/TAN after old receipts were issued; the
     * re-render shows current statutory credentials, never stale ones).
     *
     * Secondary-observation fix: tax80gNote is now re-derived from the
     * live verify80G() decision instead of being dropped to null, so
     * re-rendered PDFs / emails carry the same note the issuance did.
     *
     * @param  array<string, mixed>  $data  DataWorker bundle
     */
    public function buildForReceipt(array $data, \App\Payments\Domain\Entities\Receipt $receipt): ReceiptDocument
    {
        $payment = $data['payment'] ?? null;
        $donation = $data['donation'] ?? null;
        $campaign = $data['campaign'] ?? null;
        $trust = ($data['trust_identity'] ?? null) instanceof TrustIdentity
            ? $data['trust_identity']
            : null;
        $paymentDate = $payment !== null ? ($payment->capturedAt() ?? $payment->createdAt()) : null;

        // Re-derive the 80G block live (DB-sourced registration) so
        // re-renders match issuance.
        $note = null;
        $regNumber = $trust?->eightyGNumber;
        if ($payment !== null) {
            $live = $this->substrate->verify80G(
                pan: $receipt->donorPan(),
                amountMinor: $receipt->amountMinor(),
                currency: $receipt->currency(),
                paymentDate: $paymentDate ?? $receipt->generatedAt(),
                trust: $trust,
            );
            $note = $live['note'];
            $regNumber = $live['trust_registration_number'];
        }

        [, $campaignDescription] = $this->campaignBreakdown($campaign);

        return new ReceiptDocument(
            receiptNumber: $receipt->receiptNumber(),
            fyLabel: $this->substrate->formatFY($receipt->generatedAt()),
            campaignTitle: $receipt->campaignTitleSnapshot(),
            campaignDescription: $campaignDescription,
            donorName: $receipt->donorName(),
            donorEmail: $receipt->donorEmail(),
            donorPan: $receipt->donorPan(),
            donorAddressBlock: $this->substrate->formatAddressBlock($receipt->donorAddress()),
            amountMinor: $receipt->amountMinor(),
            currency: $receipt->currency(),
            amountDisplay: $this->substrate->formatMoney($receipt->amountMinor(), $receipt->currency()),
            amountInWords: $receipt->amountInWords() ?? $this->substrate->amountInWords($receipt->amountMinor()),
            paymentReference: $payment !== null ? $payment->id()->value() : $receipt->paymentId()->value(),
            paymentDate: $paymentDate,
            paymentDateDisplay: $paymentDate !== null ? $this->substrate->formatDate($paymentDate) : '',
            generatedAt: $receipt->generatedAt(),
            issuedDateDisplay: $this->substrate->formatDate($receipt->generatedAt()),
            isTaxDeductible: $receipt->isTaxDeductible(),
            tax80gEligible: $receipt->tax80gEligible(),
            tax80gCertificateNumber: $receipt->tax80gCertificateNumber(),
            tax80gRegistrationNumber: $regNumber,
            tax80gNote: $note,
            trustName: $this->presentational($trust?->name, fn () => $this->substrate->trustName(), 'Temple Trust'),
            trustAddress: $this->presentational($trust?->address, fn () => $this->substrate->trustAddress(), ''),
            trustEmail: $this->presentational($trust?->email, fn () => $this->substrate->trustEmail(), ''),
            trustPhone: $this->presentational($trust?->phone, fn () => $this->substrate->trustPhone(), ''),
            trustPan: $trust?->pan,
            trustTan: $trust?->tan,
            trustTwelveANumber: $trust?->twelveANumber,
            contentHash: $receipt->contentHash(),
            state: $receipt->state(),
        );
    }

    /**
     * Presentational donee fields only (name/address/email/phone):
     * DB row wins, substrate config covers the row-absent case.
     * NEVER use this for credentials — those stay DB-only (null when
     * the row is absent).
     *
     * @param  callable(): string  $fallback
     */
    private function presentational(?string $dbValue, callable $fallback, string $default): string
    {
        if ($dbValue !== null && $dbValue !== '') {
            return $dbValue;
        }
        $fb = $fallback();

        return $fb !== '' ? $fb : $default;
    }

    /**
     * Complete donation breakdown: campaign title + description.
     * Accepts DTOs (CampaignDetailDTO/SummaryDTO), arrays, or stdClass
     * rows — the campaign repository surface varies by caller.
     *
     * @return array{0: string, 1: string}
     */
    private function campaignBreakdown(mixed $campaign): array
    {
        if ($campaign === null) {
            return ['Temple donation', ''];
        }

        $title = '';
        $description = '';
        if (is_array($campaign)) {
            $title = (string) ($campaign['title'] ?? '');
            $description = (string) ($campaign['description'] ?? $campaign['short_description'] ?? $campaign['shortDescription'] ?? '');
        } elseif (is_object($campaign)) {
            $title = (string) (($campaign->title ?? null)
                ?? (method_exists($campaign, 'title') ? $campaign->title() : ''));
            $description = (string) (($campaign->description ?? null)
                ?? ($campaign->shortDescription ?? null)
                ?? ($campaign->short_description ?? null)
                ?? (method_exists($campaign, 'description') ? $campaign->description() : ''));
        }

        if ($title === '') {
            $title = 'Temple donation';
        }

        return [$title, $description];
    }
}
