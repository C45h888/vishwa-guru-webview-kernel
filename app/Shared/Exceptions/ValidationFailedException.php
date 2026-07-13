<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Thrown when an input fails business validation.
 *
 * Business validation lives in service contracts, NOT in HTTP request
 * validation. ValidationFailedException is therefore distinct from
 * Laravel's ValidationException — Laravel's exception is a request-layer
 * concern; this exception belongs to the domain.
 */
final class ValidationFailedException extends DomainException
{
    /**
     * @param array<string, list<string>> $violations
     */
    public function __construct(
        string $message,
        private readonly array $violations = [],
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return 'shared.validation.failed';
    }

    /**
     * @return array<string, list<string>>
     */
    public function violations(): array
    {
        return $this->violations;
    }

    /**
     * @param array<string, list<string>> $violations
     */
    public function withViolations(array $violations): self
    {
        return new self($this->getMessage(), $violations);
    }

    public function context(): array
    {
        return ['violations' => $this->violations];
    }
}