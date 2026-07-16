<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\InMemory;

use App\Payments\Contracts\PaymentProviderContract;
use App\Payments\Domain\Enums\Currency;

/**
 * In-memory test double for PaymentProviderContract.
 * Always enabled, supports all currencies, deterministic.
 */
final class InMemoryProviderAdapter implements PaymentProviderContract
{
    public function __construct(
        private bool $enabled = true,
        private array $supportedCurrencies = [],
        private int $minimumAmount = 100,
        private int $maximumAmount = 99_999_999,
        private int $priority = 100,
    ) {}

    public static function createDefault(): self
    {
        return new self(
            enabled: true,
            supportedCurrencies: [Currency::INR, Currency::USD, Currency::EUR],
            minimumAmount: 100,
            maximumAmount: 99_999_999,
            priority: 100,
        );
    }

    public function name(): string
    {
        return 'inmemory';
    }

    public function displayName(): string
    {
        return 'In-Memory Test Provider';
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return array<int, Currency>
     */
    public function supportedCurrencies(): array
    {
        return $this->supportedCurrencies ?: [Currency::INR, Currency::USD, Currency::EUR];
    }

    public function minimumAmount(): int
    {
        return $this->minimumAmount;
    }

    public function maximumAmount(): int
    {
        return $this->maximumAmount;
    }

    public function priority(): int
    {
        return $this->priority;
    }
}
