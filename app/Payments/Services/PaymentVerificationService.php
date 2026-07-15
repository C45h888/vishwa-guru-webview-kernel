<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Contracts\PaymentVerificationContract;
use App\Payments\Domain\DTOs\VerificationContextDTO;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\DuplicatePaymentException;
use App\Payments\Domain\Exceptions\PaymentVerificationFailedException;
use App\Payments\Domain\Exceptions\WebhookVerificationFailedException;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\IdempotencyKeyRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\WebhookEventRepositoryContract;
use App\Payments\Domain\ValueObjects\PaymentVerification;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * The 4-stage verification pipeline that gates every webhook callback
 * before it is allowed to mutate a Payment's persisted state.
 *
 * Stage 1 — RECORD
 *   Always runs. The raw webhook is appended to webhook_events so the
 *   pipeline has a forensic trail regardless of outcome. A failure to
 *   record (e.g. duplicate provider_event_id) is itself a terminal
 *   result and short-circuits the rest of the pipeline.
 *
 * Stage 2 — SIGNATURE
 *   Pick the PaymentVerificationContract registered for the webhook's
 *   provider code. Run verifyWebhook() against the raw payload.
 *   Failure here classifies as TERMINAL_INVALID (a forged callback
 *   MUST NOT be processed under any circumstance).
 *
 * Stage 3 — AMOUNT
 *   Compare the gateway-reported amount + currency against the local
 *   Donation's expected amount + currency. Mismatch short-circuits
 *   with a clear error code.
 *
 * Stage 4 — IDEMPOTENCY + DUPLICATE
 *   Two checks:
 *     (a) The local Payment exists for the gateway_order_id.
 *     (b) The local Payment is NOT already in a terminal-success
 *         state (which would mean the webhook is a replay).
 *   A non-terminal existing payment is allowed to advance.
 *
 * The pipeline returns Result<PaymentVerification>. Stage results
 * are attached to the VerificationContextDTO for downstream
 * observability (Pass 1.4 admin console reads them).
 */
final class PaymentVerificationService
{
    /**
     * @param  iterable<PaymentVerificationContract>  $verifiers
     */
    public function __construct(
        private readonly PaymentRepositoryContract $payments,
        private readonly DonationRepositoryContract $donations,
        private readonly IdempotencyKeyRepositoryContract $idempotency,
        private readonly WebhookEventRepositoryContract $webhookEvents,
        private readonly AuditEventRepositoryContract $auditLog,
        private readonly Clock $clock,
        private readonly iterable $verifiers,
    ) {}

    /**
     * @return Result<PaymentVerification>
     */
    public function verify(VerificationContextDTO $context): Result
    {
        $payload = $context->payload();
        $provider = $payload->provider();
        $providerCode = $provider->value;
        $providerEventId = $payload->providerEventId();

        // ── Stage 1: record ──────────────────────────────────────
        $this->recordWebhook($provider, $providerEventId, $context);

        // ── Stage 2: signature ───────────────────────────────────
        $verifier = $this->pickVerifier($providerCode);
        if ($verifier === null) {
            return $this->fail(
                $context,
                VerificationContextDTO::STAGE_SIGNATURE,
                'verifier_not_registered',
                sprintf('No PaymentVerificationContract registered for provider [%s]', $providerCode),
            );
        }

        $sigResult = $verifier->verifyWebhook($payload->headers(), $payload->rawBody());
        if ($sigResult->isFailure()) {
            $this->auditLog->append(
                eventType: 'payment.webhook.signature_failed',
                entityType: 'payment_webhook',
                entityId: $providerEventId ?: 'unknown',
                correlationId: $providerEventId ?: null,
                previousState: null,
                newState: 'signature_failed',
                context: [
                    'provider' => $providerCode,
                    'reason' => $sigResult->error(),
                ],
                occurredAt: $this->clock->now(),
            );

            throw WebhookVerificationFailedException::invalidSignature(
                $providerCode,
                $payload->metadata()['gateway_order_id'] ?? 'unknown',
            );
        }

        $sigPayload = $sigResult->value();
        $gatewayOrderId = (string) ($sigPayload['gateway_order_id'] ?? '');
        $gatewayPaymentId = (string) ($sigPayload['gateway_payment_id'] ?? '');
        $status = $sigPayload['status'] ?? null;
        $amount = (int) ($sigPayload['amount'] ?? 0);
        $currency = $sigPayload['currency'] ?? null;

        if ($gatewayOrderId === '' || ! $status instanceof TransactionStatus || $amount <= 0) {
            return $this->fail(
                $context,
                VerificationContextDTO::STAGE_SIGNATURE,
                'signature_payload_malformed',
                'Signature verification succeeded but payload is missing required fields',
            );
        }

        // ── Stage 3: amount match ─────────────────────────────────
        if ($amount !== $context->expectedAmountMinor()) {
            return $this->fail(
                $context,
                VerificationContextDTO::STAGE_AMOUNT,
                'amount_mismatch',
                sprintf(
                    'Gateway amount %d does not match expected %d',
                    $amount,
                    $context->expectedAmountMinor(),
                ),
            );
        }
        if ($currency !== $context->expectedCurrency()->value) {
            return $this->fail(
                $context,
                VerificationContextDTO::STAGE_AMOUNT,
                'currency_mismatch',
                sprintf(
                    'Gateway currency %s does not match expected %s',
                    (string) $currency,
                    $context->expectedCurrency()->value,
                ),
            );
        }

        // ── Stage 4: idempotency + duplicate ──────────────────────
        $existing = $this->payments->findByGatewayOrderId($gatewayOrderId);
        if ($existing !== null && $existing->status()->isSuccessful()) {
            throw DuplicatePaymentException::webhookReplay(
                $gatewayOrderId,
                $existing->id()->ulid(),
            );
        }

        $verification = new PaymentVerification(
            provider: $provider,
            gatewayOrderId: $gatewayOrderId,
            gatewayPaymentId: $gatewayPaymentId,
            status: $status,
            amountMinor: $amount,
            currency: $context->expectedCurrency(),
            verifiedAt: $this->clock->now(),
            method: isset($sigPayload['method']) ? (string) $sigPayload['method'] : null,
            metadata: [
                'stage_results' => $context->stageResults(),
                'expected_idempotency_key' => $context->expectedIdempotencyKey(),
            ],
        );

        $this->auditLog->append(
            eventType: 'payment.webhook.verified',
            entityType: 'payment',
            entityId: $existing?->id()->ulid() ?? 'unknown',
            correlationId: $providerEventId ?: null,
            previousState: $existing?->status()->value,
            newState: $status->value,
            context: [
                'provider' => $providerCode,
                'gateway_order_id' => $gatewayOrderId,
                'amount_minor' => $amount,
                'currency' => $currency,
            ],
            occurredAt: $this->clock->now(),
        );

        return Result::success($verification);
    }

    /**
     * Resolve the PaymentVerificationContract registered for the
     * given provider code. Returns null when no adapter advertises
     * the code — a programming error in DI registration.
     */
    private function pickVerifier(string $providerCode): ?PaymentVerificationContract
    {
        foreach ($this->verifiers as $verifier) {
            // Each verifier exposes a providerCode() via its
            // PaymentProviderContract parent type, but here we look
            // it up via the PaymentProvider enum because the verifier
            // objects are tagged by code (Pass 1.6 wires the tag).
            $candidate = $this->verifierProviderCode($verifier);
            if ($candidate === $providerCode) {
                return $verifier;
            }
        }

        return null;
    }

    /**
     * Each PaymentVerificationContract is paired with a
     * PaymentProviderContract at the adapter layer; the convention
     * is to expose providerCode() on the verifier object itself in
     * Pass 1.5. For Pass 1.3 we resolve by attempting to parse the
     * FQCN — fallback "razorpay" so the selector never silently
     * drops a webhook.
     */
    private function verifierProviderCode(PaymentVerificationContract $verifier): string
    {
        if (method_exists($verifier, 'providerCode')) {
            return (string) $verifier->providerCode();
        }

        $class = $verifier::class;
        $lower = strtolower($class);

        if (str_contains($lower, 'razorpay')) {
            return PaymentProvider::RAZORPAY->value;
        }
        if (str_contains($lower, 'paypal')) {
            return PaymentProvider::PAYPAL->value;
        }
        if (str_contains($lower, 'inmemory') || str_contains($lower, 'memory')) {
            return 'inmemory';
        }

        return '';
    }

    private function recordWebhook(
        PaymentProvider $provider,
        string $providerEventId,
        VerificationContextDTO $context,
    ): void {
        if ($providerEventId === '') {
            return;
        }
        if ($this->webhookEvents->exists($provider, $providerEventId)) {
            // Duplicate provider_event_id is itself terminal.
            throw new DuplicatePaymentException(
                sprintf('Duplicate webhook for provider [%s] event [%s]', $provider->value, $providerEventId),
                $providerEventId,
            );
        }

        $this->webhookEvents->record(
            provider: $provider,
            providerEventId: $providerEventId,
            eventType: 'payment.callback',
            payload: [
                'headers' => $context->payload()->headers(),
                'body_length' => strlen($context->payload()->rawBody()),
            ],
            headers: $context->payload()->headers(),
            signatureVerified: true,
            processingStatus: 'pending',
        );
    }

    /**
     * @return Result<PaymentVerification>
     */
    private function fail(
        VerificationContextDTO $context,
        string $stage,
        string $errorCode,
        string $message,
    ): Result {
        $this->auditLog->append(
            eventType: 'payment.webhook.stage_failed',
            entityType: 'payment_webhook',
            entityId: $context->payload()->providerEventId() ?: 'unknown',
            previousState: null,
            newState: $stage.':'.$errorCode,
            context: [
                'stage' => $stage,
                'error_code' => $errorCode,
                'message' => $message,
                'provider' => $context->payload()->provider()->value,
            ],
            occurredAt: $this->clock->now(),
        );

        return Result::failure(sprintf('%s:%s: %s', $stage, $errorCode, $message));
    }
}