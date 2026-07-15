<?php

declare(strict_types=1);

namespace App\Payments\Domain\DTOs;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\ValueObjects\WebhookPayload;
use App\Shared\ValueObjects\Identifier;
use InvalidArgumentException;

/**
 * Bundle of data passed through the PaymentVerificationService's
 * 4-stage pipeline (signature, amount match, idempotency, duplicate).
 *
 * Each stage may attach a result to stageResults so downstream stages
 * and the eventual orchestrator can introspect what happened at each
 * step. Stages never throw — they record failure into stageResults and
 * let the orchestrator decide whether to short-circuit.
 */
final readonly class VerificationContextDTO
{
    public const STAGE_SIGNATURE = 'signature';
    public const STAGE_AMOUNT = 'amount';
    public const STAGE_IDEMPOTENCY = 'idempotency';
    public const STAGE_DUPLICATE = 'duplicate';

    /**
     * @param  array<string, array<string, mixed>>  $stageResults
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        private WebhookPayload $payload,
        private Identifier $donationId,
        private Identifier $paymentId,
        private int $expectedAmountMinor,
        private Currency $expectedCurrency,
        private string $expectedIdempotencyKey,
        private array $stageResults = [],
        private array $metadata = [],
    ) {
        if ($expectedAmountMinor <= 0) {
            throw new InvalidArgumentException(
                'VerificationContext expectedAmountMinor must be positive'
            );
        }
        if (empty($expectedIdempotencyKey)) {
            throw new InvalidArgumentException(
                'VerificationContext expectedIdempotencyKey cannot be empty'
            );
        }
    }

    public function payload(): WebhookPayload
    {
        return $this->payload;
    }

    public function donationId(): Identifier
    {
        return $this->donationId;
    }

    public function paymentId(): Identifier
    {
        return $this->paymentId;
    }

    public function expectedAmountMinor(): int
    {
        return $this->expectedAmountMinor;
    }

    public function expectedCurrency(): Currency
    {
        return $this->expectedCurrency;
    }

    public function expectedIdempotencyKey(): string
    {
        return $this->expectedIdempotencyKey;
    }

    public function stageResults(): array
    {
        return $this->stageResults;
    }

    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    public function withStageResult(string $stage, array $result): self
    {
        $results = $this->stageResults;
        $results[$stage] = $result;

        return new self(
            $this->payload,
            $this->donationId,
            $this->paymentId,
            $this->expectedAmountMinor,
            $this->expectedCurrency,
            $this->expectedIdempotencyKey,
            $results,
            $this->metadata,
        );
    }

    public function withMetadata(array $extra): self
    {
        return new self(
            $this->payload,
            $this->donationId,
            $this->paymentId,
            $this->expectedAmountMinor,
            $this->expectedCurrency,
            $this->expectedIdempotencyKey,
            $this->stageResults,
            array_merge($this->metadata, $extra),
        );
    }

    /**
     * Get a single stage's result.
     *
     * @return array<string, mixed>|null
     */
    public function stageResult(string $stage): ?array
    {
        return $this->stageResults[$stage] ?? null;
    }

    /**
     * Whether a given stage passed.
     */
    public function stagePassed(string $stage): bool
    {
        $r = $this->stageResults[$stage] ?? null;
        if ($r === null) {
            return false;
        }

        return ($r['passed'] ?? false) === true;
    }

    public function toArray(): array
    {
        return [
            'payload' => $this->payload->toArray(),
            'donation_id' => $this->donationId->value(),
            'payment_id' => $this->paymentId->value(),
            'expected_amount_minor' => $this->expectedAmountMinor,
            'expected_currency' => $this->expectedCurrency->value,
            'expected_idempotency_key' => $this->expectedIdempotencyKey,
            'stage_results' => $this->stageResults,
            'metadata' => $this->metadata,
        ];
    }
}
