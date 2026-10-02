<?php

declare(strict_types=1);

namespace App\Payments\Receipts\Workers;

use App\Payments\Domain\DTOs\ReceiptDocument;
use App\Payments\Receipts\ReceiptSubstrate;

/**
 * TypesWorker — turns transported data into the typed ReceiptDocument.
 *
 * Semantic boundary: everything about TYPING lives here (money, dates,
 * FY, 80G block, address block), and every rule is imported from the
 * parent substrate (ReceiptSubstrate) — the substrate is the single
 * owner of generation logic; workers only move it across boundaries.
 *
 * The 80G determination is the substrate's simple verify80G() function;
 * this worker calls it and places the result into the typed document.
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

        $campaignTitle = 'Temple donation';
        if ($campaign !== null && ($campaign->title ?? '') !== '') {
            $campaignTitle = (string) $campaign->title;
        }

        $amountMinor = $payment !== null ? $payment->amountMinor() : 0;
        $currency = $payment !== null ? $payment->currency() : \App\Payments\Domain\Enums\Currency::INR;
        $paymentDate = $payment !== null ? ($payment->capturedAt() ?? $payment->createdAt()) : null;

        $donorPan = $donation?->donorPanSnapshot();
        $certificate = $this->substrate->verify80G(
            pan: $donorPan,
            amountMinor: $amountMinor,
            currency: $currency,
            paymentDate: $paymentDate ?? $this->substrate->now(),
        );

        return new ReceiptDocument(
            receiptNumber: $receiptNumber,
            fyLabel: $this->substrate->formatFY($paymentDate ?? $this->substrate->now()),
            campaignTitle: $campaignTitle,
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
            trustName: $this->substrate->trustName(),
            trustAddress: $this->substrate->trustAddress(),
            trustEmail: $this->substrate->trustEmail(),
            trustPhone: $this->substrate->trustPhone(),
            trustPan: $this->substrate->trustPan(),
        );
    }

    /**
     * Build the document for an ALREADY-PERSISTED receipt (web page,
     * email, re-render). Snapshot fields come from the receipt row — the
     * row is the truth — while payment provenance comes from the
     * transported payment.
     *
     * @param  array<string, mixed>  $data  DataWorker bundle
     */
    public function buildForReceipt(array $data, \App\Payments\Domain\Entities\Receipt $receipt): ReceiptDocument
    {
        $payment = $data['payment'] ?? null;
        $paymentDate = $payment !== null ? ($payment->capturedAt() ?? $payment->createdAt()) : null;

        return new ReceiptDocument(
            receiptNumber: $receipt->receiptNumber(),
            fyLabel: $this->substrate->formatFY($receipt->generatedAt()),
            campaignTitle: $receipt->campaignTitleSnapshot(),
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
            tax80gRegistrationNumber: $this->substrate->trustRegistrationNumber(),
            tax80gNote: null,
            trustName: $this->substrate->trustName(),
            trustAddress: $this->substrate->trustAddress(),
            trustEmail: $this->substrate->trustEmail(),
            trustPhone: $this->substrate->trustPhone(),
            trustPan: $this->substrate->trustPan(),
            contentHash: $receipt->contentHash(),
            state: $receipt->state(),
        );
    }
}
