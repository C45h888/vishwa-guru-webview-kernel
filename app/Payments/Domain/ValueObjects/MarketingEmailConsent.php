<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Affirmative, optional consent for future fundraising email.
 *
 * It is intentionally not part of CheckoutPolicyAcceptance: a donor can
 * complete a donation without granting this marketing permission.
 */
final readonly class MarketingEmailConsent
{
    public function __construct(
        private string $consentVersion,
        private DateTimeImmutable $consentedAt,
    ) {
        if (trim($consentVersion) === '') {
            throw new InvalidArgumentException('Marketing consent version cannot be empty');
        }
    }

    public function consentVersion(): string
    {
        return $this->consentVersion;
    }

    public function consentedAt(): DateTimeImmutable
    {
        return $this->consentedAt;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return [
            'marketing_email_consent_version' => $this->consentVersion,
            'marketing_email_consented_at' => $this->consentedAt->format(DATE_ATOM),
        ];
    }
}
