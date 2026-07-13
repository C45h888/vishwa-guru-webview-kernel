<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Enums\TransactionStatus;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

/**
 * Records the terminal state of a failed payment.
 * Failure states are immutable — once recorded, no transition occurs.
 *
 * Captures:
 *   - the gateway response (provider, status, error code)
 *   - the failure context (when, why, retry feasibility)
 *   - the audit trail (correlation ids, operator notes)
 */
interface FailureStateContract
{
    public function identifier(): Identifier;

    public function transactionIdentifier(): Identifier;

    public function gatewayOrderId(): string;

    public function providerName(): string;

    public function finalStatus(): TransactionStatus;

    public function failureCode(): string;

    public function failureReason(): string;

    public function occurredAt(): DateTimeImmutable;

    public function isRetryable(): bool;

    public function retryAttempts(): int;

    public function correlationId(): string;

    public function context(): array;
}