<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Enums\Currency;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;
use DateTimeImmutable;

/**
 * Pure-domain 80G eligibility validator.
 *
 * Implements Income Tax Act 1961 §80G simplified rules:
 *   - Trust must be 80G-registered (config flag)
 *   - Donor must have a valid PAN (for deductible donations)
 *   - Donation must meet the minimum certificate threshold
 *
 * No I/O — all inputs are passed as method arguments.
 */
final class Receipt80GValidator
{
    private const array VALID_PAN_PATTERN = [
        '/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/i', // Standard Indian PAN
    ];

    public function __construct(
        private readonly ConfigurationContract $config,
    ) {}

    /**
     * Determine 80G eligibility for a donation.
     *
     * @param  string|null       $pan           Donor's PAN (if provided)
     * @param  int              $amountMinor   Donation amount in minor units
     * @param  Currency          $currency      Must be INR for 80G
     * @param  DateTimeImmutable $paymentDate
     *
     * @return Result<array{eligible: bool, reason: string|null, certificate_required: bool, certificate_number: string|null}>
     */
    public function isEligible(
        ?string $pan,
        int $amountMinor,
        Currency $currency,
        DateTimeImmutable $paymentDate,
    ): Result {
        // Rule 1: Only INR donations qualify
        if ($currency !== Currency::INR) {
            return Result::success([
                'eligible' => false,
                'reason' => '80G exemption only available for INR donations',
                'certificate_required' => false,
                'certificate_number' => null,
            ]);
        }

        // Rule 2: Trust must be 80G registered
        $trustRegistered = $this->config->boolean('receipts.80g.trust_registered', false);
        if (! $trustRegistered) {
            return Result::success([
                'eligible' => false,
                'reason' => 'Trust is not registered under Section 80G',
                'certificate_required' => false,
                'certificate_number' => null,
            ]);
        }

        // Rule 3: Certificate required for donations above threshold
        $threshold = $this->config->integer('receipts.80g.certificate_threshold_minor', 500_00);
        $needsCertificate = $this->certificateRequired($amountMinor, $pan !== null && $pan !== '');

        if ($needsCertificate && ($pan === null || $pan === '')) {
            return Result::success([
                'eligible' => false,
                'reason' => 'PAN is mandatory for donations exceeding ₹500 to issue an 80G certificate',
                'certificate_required' => true,
                'certificate_number' => null,
            ]);
        }

        // Rule 4: Valid PAN if provided
        if ($pan !== null && $pan !== '' && ! $this->isValidPan($pan)) {
            return Result::success([
                'eligible' => false,
                'reason' => 'Provided PAN is not in a valid format',
                'certificate_required' => $needsCertificate,
                'certificate_number' => null,
            ]);
        }

        // Eligible — but certificate only issued above threshold
        $certNumber = null;
        if ($needsCertificate && $this->config->get('receipts.80g.trust_registration_number') !== null) {
            $certNumber = $this->generateCertificateNumber($paymentDate);
        }

        return Result::success([
            'eligible' => true,
            'reason' => null,
            'certificate_required' => $needsCertificate,
            'certificate_number' => $certNumber,
        ]);
    }

    /**
     * Determine whether an 80G certificate is required for this donation.
     * Per ITD rules: certificate needed above ₹500 if PAN provided; above ₹2,000 without PAN.
     */
    public function certificateRequired(int $amountMinor, bool $hasPan): bool
    {
        if ($hasPan) {
            $threshold = $this->config->integer('receipts.80g.certificate_threshold_minor', 500_00);

            return $amountMinor > $threshold;
        }

        // Without PAN, certificate threshold is lower (₹2,000 per current rules)
        return $amountMinor > 200_00;
    }

    /**
     * Generate an 80G certificate number for this donation.
     * Format: 80G/{FY}/{Serial}
     */
    private function generateCertificateNumber(DateTimeImmutable $paymentDate): string
    {
        $year = (int) $paymentDate->format('Y');
        $month = (int) $paymentDate->format('n');
        $fy = $month >= 4 ? $year : ($year - 1);
        $regNumber = (string) $this->config->get('receipts.80g.trust_registration_number', 'REG');

        $serial = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        return sprintf('80G/%d/%s/%s', $fy, $regNumber, $serial);
    }

    private function isValidPan(string $pan): bool
    {
        foreach (self::VALID_PAN_PATTERN as $pattern) {
            if (preg_match($pattern, $pan)) {
                return true;
            }
        }

        return false;
    }
}
