<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Entities\Receipt;
use App\Payments\Domain\Enums\Currency;

/**
 * Transforms domain entities into the data arrays consumed by Blade templates.
 *
 * This class is pure transformation — no I/O, no service calls.
 * It formats dates, amounts, addresses, and tax fields for display.
 */
final class ReceiptFormatter
{
    /**
     * Build the full data array for the receipt PDF template.
     *
     * @param  Receipt   $receipt
     * @param  Payment   $payment
     * @param  Donation  $donation
     * @param  array<string, string>  $trustInfo  keys: name, address, email, phone, pan
     * @return array<string, mixed>
     */
    public function format(
        Receipt $receipt,
        Payment $payment,
        Donation $donation,
        array $trustInfo = [],
    ): array {
        return [
            // Trust info
            'trust_name' => $trustInfo['name'] ?? 'Temple Trust',
            'trust_address' => $trustInfo['address'] ?? '',
            'trust_email' => $trustInfo['email'] ?? '',
            'trust_phone' => $trustInfo['phone'] ?? '',
            'trust_pan' => $trustInfo['pan'] ?? '',

            // Receipt metadata
            'receipt_number' => $receipt->receiptNumber(),
            'issued_date' => $this->formatDate($receipt->generatedAt()),
            'issued_date_formatted' => $this->formatDateLong($receipt->generatedAt()),
            'issued_date_fy' => $this->formatFY($receipt->generatedAt()),

            // Donor info
            'donor_name' => $receipt->donorName(),
            'donor_email' => $receipt->donorEmail(),
            'donor_pan' => $receipt->donorPan(),
            'donor_address' => $this->buildAddressBlock($donation->donorAddressSnapshot()),

            // Payment info
            'payment_id' => $payment->id()->value(),
            'gateway' => 'razorpay', // TODO: inject from Payment entity when provider is tracked
            'payment_date' => $this->formatDate($payment->capturedAt() ?? $payment->createdAt()),
            'payment_method' => 'UPI / Net Banking / Card', // TODO: extend Payment entity

            // Amount info
            'currency' => $payment->currency()->value,
            'currency_symbol' => $payment->currency()->symbol(),
            'amount_minor' => $payment->amountMinor(),
            'amount_display' => $this->formatAmount($payment->amountMinor(), $payment->currency()),
            'amount_in_words' => $receipt->amountInWords() ?? '',
            'tax_amount_minor' => 0, // populated from Payment.feeMinor if needed

            // Campaign
            'campaign_title' => $receipt->campaignTitleSnapshot(),
            'campaign_id' => $receipt->campaignId()->value(),

            // Tax
            'is_tax_deductible' => $receipt->isTaxDeductible(),
            'tax_80g_eligible' => $receipt->tax80gEligible(),
            'tax_80g_certificate_number' => $receipt->tax80gCertificateNumber(),
            'pan_required_note' => $this->panNote($receipt),

            // Receipt file
            'content_hash' => $receipt->contentHash(),
        ];
    }

    /**
     * Build a compact summary array for the short receipt email body.
     *
     * @return array<string, mixed>
     */
    public function formatSummary(Receipt $receipt): array
    {
        return [
            'receipt_number' => $receipt->receiptNumber(),
            'donor_name' => $receipt->donorName(),
            'amount_display' => $receipt->currency()->symbol() . ' ' . number_format(
                $receipt->amountMinor() / 100,
                2,
            ),
            'issued_date' => $this->formatDate($receipt->generatedAt()),
        ];
    }

    /**
     * Build a formatted postal address block from a donor address array.
     *
     * @param  array<string, string>|null  $address
     */
    public function buildAddressBlock(?array $address): string
    {
        if ($address === null || $address === []) {
            return '';
        }

        $lines = [];

        if (isset($address['line1']) && $address['line1'] !== '') {
            $lines[] = $address['line1'];
        }
        if (isset($address['line2']) && $address['line2'] !== '') {
            $lines[] = $address['line2'];
        }
        if (isset($address['city']) && $address['city'] !== '') {
            $cityLine = $address['city'];
            if (isset($address['state']) && $address['state'] !== '') {
                $cityLine .= ', ' . $address['state'];
            }
            if (isset($address['pincode']) && $address['pincode'] !== '') {
                $cityLine .= ' ' . $address['pincode'];
            }
            $lines[] = $cityLine;
        }
        if (isset($address['country']) && $address['country'] !== '' && $address['country'] !== 'India') {
            $lines[] = $address['country'];
        }

        return implode("\n", $lines);
    }

    /**
     * Format a minor-unit amount with currency symbol.
     */
    private function formatAmount(int $minorAmount, Currency $currency): string
    {
        $major = $minorAmount / 100;

        return $currency->symbol() . ' ' . number_format($major, 2);
    }

    /**
     * Format a DateTimeImmutable to a compact date string.
     */
    private function formatDate(\DateTimeImmutable $date): string
    {
        return $date->format('d-M-y');
    }

    /**
     * Format a DateTimeImmutable to a long-form date string.
     */
    private function formatDateLong(\DateTimeImmutable $date): string
    {
        return $date->format('d F Y');
    }

    /**
     * Return the FY label for a date (e.g. "FY 2026-27").
     */
    private function formatFY(\DateTimeImmutable $date): string
    {
        $year = (int) $date->format('Y');
        $month = (int) $date->format('n');

        $fyStart = $month >= 4 ? $year : ($year - 1);
        $fyEnd = $fyStart + 1;

        return sprintf('FY %d-%02d', $fyStart, $fyEnd % 100);
    }

    /**
     * Build a PAN note for the receipt if 80G is involved.
     */
    private function panNote(Receipt $receipt): string
    {
        if (! $receipt->tax80gEligible()) {
            return '';
        }

        if ($receipt->donorPan() === null || $receipt->donorPan() === '') {
            return 'Note: PAN details not provided. Tax deduction under Section 80G requires valid PAN.';
        }

        return '';
    }
}
