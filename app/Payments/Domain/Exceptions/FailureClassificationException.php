<?php

declare(strict_types=1);

namespace App\Payments\Domain\Exceptions;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Shared\Exceptions\DomainException;

/**
 * Thrown when the FailureStateManager cannot assign a classification
 * to a payment failure.
 *
 * Examples:
 *   - Gateway returned a status outside TransactionStatus
 *   - Missing fields required to classify the failure
 *   - Provider code is unknown to the classification registry
 */
final class FailureClassificationException extends DomainException
{
    public function __construct(
        string $message,
        private readonly string $providerCode,
        private readonly ?TransactionStatus $observedStatus = null,
    ) {
        parent::__construct($message);
    }

    public static function unknownProvider(string $providerCode): self
    {
        return new self(
            sprintf('No classification rule registered for provider [%s]', $providerCode),
            $providerCode,
        );
    }

    public static function unknownStatus(string $providerCode, string $rawStatus): self
    {
        return new self(
            sprintf('Cannot classify failure from provider [%s] with status [%s]', $providerCode, $rawStatus),
            $providerCode,
            null,
        );
    }

    public static function missingContext(string $providerCode, string $missingField): self
    {
        return new self(
            sprintf(
                'Cannot classify failure from provider [%s]: required field [%s] missing',
                $providerCode,
                $missingField,
            ),
            $providerCode,
            null,
        );
    }

    public function errorCode(): string
    {
        return 'payments.failure.classification.failed';
    }

    public function providerCode(): string
    {
        return $this->providerCode;
    }

    public function observedStatus(): ?TransactionStatus
    {
        return $this->observedStatus;
    }

    public function context(): array
    {
        return [
            'provider_code' => $this->providerCode,
            'observed_status' => $this->observedStatus?->value,
        ];
    }
}