<?php

declare(strict_types=1);

namespace App\Payments\Infrastructure\Adapters\InMemory;

use App\Payments\Contracts\PaymentVerificationContract;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\WebhookVerificationFailedException;
use App\Shared\Support\Result;

/**
 * In-memory test double for PaymentVerificationContract.
 *
 * Configurable via:
 *   $adapter = (new InMemoryVerificationAdapter('test-secret'))
 *       ->setNextWebhookResult(Result::success([...]))
 *       ->setAlwaysVerifySignature(true|false);
 */
final class InMemoryVerificationAdapter implements PaymentVerificationContract
{
    private ?Result $nextWebhookResult = null;
    private bool $alwaysVerifySignature = true;
    private ?bool $lastVerificationResult = null;

    /** @var list<array{headers: array, payload: string, result: Result}> */
    private array $webhookCalls = [];

    public function __construct(
        private string $webhookSecret = 'test-secret',
    ) {}

    /**
     * Set the result for the next verifyWebhook() call.
     *
     * @param Result<array{gateway_order_id: string, gateway_payment_id: string, status: TransactionStatus, amount: int}> $result
     */
    public function setNextWebhookResult(Result $result): self
    {
        $this->nextWebhookResult = $result;

        return $this;
    }

    /**
     * Configure whether verifySignature() always returns true.
     */
    public function setAlwaysVerifySignature(bool $value): self
    {
        $this->alwaysVerifySignature = $value;

        return $this;
    }

    /**
     * Reset recorded calls.
     */
    public function resetCalls(): self
    {
        $this->webhookCalls = [];

        return $this;
    }

    /**
     * @return list<array{headers: array, payload: string, result: Result}>
     */
    public function getWebhookCalls(): array
    {
        return $this->webhookCalls;
    }

    public function getLastVerificationResult(): ?bool
    {
        return $this->lastVerificationResult;
    }

    public function verifyWebhook(array $headers, string $payload): Result
    {
        $result = $this->nextWebhookResult
            ?? Result::failure(
                WebhookVerificationFailedException::malformedPayload(
                    'inmemory',
                    'No scripted webhook result',
                )->getMessage(),
            );

        $this->webhookCalls[] = [
            'headers' => $headers,
            'payload' => $payload,
            'result' => $result,
        ];
        $this->nextWebhookResult = null;

        return $result;
    }

    public function verifySignature(string $payload, string $signature): bool
    {
        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);
        $result = hash_equals($expected, $signature) && $this->alwaysVerifySignature;

        $this->lastVerificationResult = $result;

        return $result;
    }

    public function generateSignature(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->webhookSecret);
    }
}
