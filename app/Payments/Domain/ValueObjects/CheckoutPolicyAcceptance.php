<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Required, transaction-scoped evidence that a donor accepted the Terms
 * and acknowledged the Privacy Notice shown at checkout.
 */
final readonly class CheckoutPolicyAcceptance
{
    public function __construct(
        private string $termsVersion,
        private DateTimeImmutable $termsAcceptedAt,
        private string $privacyVersion,
        private DateTimeImmutable $privacyAcknowledgedAt,
    ) {
        if (trim($termsVersion) === '') {
            throw new InvalidArgumentException('Terms policy version cannot be empty');
        }
        if (trim($privacyVersion) === '') {
            throw new InvalidArgumentException('Privacy policy version cannot be empty');
        }
    }

    public function termsVersion(): string
    {
        return $this->termsVersion;
    }

    public function termsAcceptedAt(): DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function privacyVersion(): string
    {
        return $this->privacyVersion;
    }

    public function privacyAcknowledgedAt(): DateTimeImmutable
    {
        return $this->privacyAcknowledgedAt;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'terms_version' => $this->termsVersion,
            'terms_accepted_at' => $this->termsAcceptedAt->format(DATE_ATOM),
            'privacy_notice_version' => $this->privacyVersion,
            'privacy_notice_acknowledged_at' => $this->privacyAcknowledgedAt->format(DATE_ATOM),
        ];
    }
}
