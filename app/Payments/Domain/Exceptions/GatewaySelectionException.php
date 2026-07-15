<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Payments\Domain\Enums\Currency;
use App\Shared\Exceptions\DomainException;

/**
 * Thrown when the PaymentProviderSelector cannot resolve a gateway
 * that satisfies a PaymentIntent's constraints.
 *
 * Examples:
 *   - No registered provider supports the requested currency
 *   - Amount outside every enabled provider's supported range
 *   - All candidate providers are disabled in the current environment
 */
final class GatewaySelectionException extends DomainException
{
    public function __construct(
        string $message,
        private readonly Currency $currency,
        private readonly int $amountMinor,
        private readonly array $candidateProviders = [],
    ) {
        parent::__construct($message);
    }

    public static function noProviderForCurrency(Currency $currency, array $candidates = []): self
    {
        $names = array_map(static fn ($p) => (string) $p, $candidates);

        return new self(
            sprintf(
                'No payment provider supports currency [%s]. Available: [%s]',
                $currency->value,
                $names === [] ? 'none' : implode(', ', $names),
            ),
            $currency,
            0,
            $candidates,
        );
    }

    public static function amountOutOfRange(Currency $currency, int $amountMinor, array $candidates = []): self
    {
        $names = array_map(static fn ($p) => (string) $p, $candidates);

        return new self(
            sprintf(
                'Amount %d %s is outside every enabled provider range. Candidates: [%s]',
                $amountMinor,
                $currency->value,
                $names === [] ? 'none' : implode(', ', $names),
            ),
            $currency,
            $amountMinor,
            $candidates,
        );
    }

    public static function noEnabledProvider(Currency $currency): self
    {
        return new self(
            sprintf('All providers for currency [%s] are disabled in this environment', $currency->value),
            $currency,
            0,
        );
    }

    public function errorCode(): string
    {
        return 'payments.gateway.selection.failed';
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function amountMinor(): int
    {
        return $this->amountMinor;
    }

    public function candidateProviders(): array
    {
        return $this->candidateProviders;
    }

    public function context(): array
    {
        return [
            'currency' => $this->currency->value,
            'amount_minor' => $this->amountMinor,
            'candidate_providers' => array_map(
                static fn ($p) => (string) $p,
                $this->candidateProviders,
            ),
        ];
    }
}