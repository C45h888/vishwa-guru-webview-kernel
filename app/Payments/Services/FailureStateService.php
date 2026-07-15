<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Domain\Entities\FailureState;
use App\Payments\Domain\Enums\FailureClassification;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\FailureClassificationException;
use App\Payments\Domain\Exceptions\PaymentStateTransitionException;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\FailureStateRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\ValueObjects\PaymentVerification;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

/**
 * Owns the lifecycle of payment failures.
 *
 * Non-final so unit tests can substitute a recording stub via
 * inheritance; production code resolves through DI and never sees
 * a subclass.
 *
 * Responsibilities:
 *   - classify() a raw gateway failure into a FailureClassification
 *   - record() the failure as a persisted FailureState row + audit event
 *   - retry() a recoverable failure (re-runs verification pipeline)
 *   - markResolved() a manually-resolved failure (operator or auto)
 *
 * The service does NOT touch the gateway directly. retry() re-uses
 * the supplied PaymentVerification (produced by the verification
 * pipeline) to decide whether the failure has been cleared.
 */
class FailureStateService
{
    public function __construct(
        private readonly FailureStateRepositoryContract $failureStates,
        private readonly PaymentRepositoryContract $payments,
        private readonly AuditEventRepositoryContract $auditLog,
        private readonly PaymentStateMachine $paymentStateMachine,
        private readonly Clock $clock,
    ) {}

    /**
     * Persist a new FailureState and emit the corresponding audit event.
     *
     * @param  array<string, mixed>  $metadata  Diagnostic context (raw gateway response, etc.)
     * @param  array<string, mixed>  $context   Domain context (campaign id, donor id, etc.)
     */
    public function record(
        EntityId $paymentId,
        string $providerCode,
        string $gatewayOrderId,
        TransactionStatus $observedStatus,
        string $failureCode,
        ?string $failureReason = null,
        ?FailureClassification $classification = null,
        array $metadata = [],
        array $context = [],
    ): FailureState {
        $classification ??= $this->classify(
            $providerCode,
            $gatewayOrderId,
            $observedStatus->value,
            $failureCode,
        );

        $correlationId = $context['correlation_id']
            ?? bin2hex(random_bytes(8));

        $failure = FailureState::record(
            paymentId: $paymentId,
            classification: $classification,
            failureCode: $failureCode,
            finalStatus: $observedStatus,
            providerCode: $providerCode,
            gatewayOrderId: $gatewayOrderId,
            correlationId: $correlationId,
            failureReason: $failureReason,
            failureMetadata: $metadata,
            context: $context,
        );

        $this->failureStates->save($failure);

        $this->auditLog->append(
            eventType: 'payment.failure.recorded',
            entityType: FailureState::ENTITY_TYPE,
            entityId: $failure->id()->ulid(),
            actor: $context['actor'] ?? null,
            correlationId: $correlationId,
            previousState: null,
            newState: $classification->value,
            context: [
                'payment_id' => $paymentId->ulid(),
                'provider_code' => $providerCode,
                'gateway_order_id' => $gatewayOrderId,
                'observed_status' => $observedStatus->value,
                'failure_code' => $failureCode,
                'failure_reason' => $failureReason,
                'classification' => $classification->value,
                'is_retryable' => $classification->isRetryable(),
            ],
            occurredAt: $this->clock->now(),
        );

        return $failure;
    }

    /**
     * Map a (providerCode, rawStatus, failureCode) tuple onto a
     * FailureClassification. Deterministic table-driven lookup;
     * anything not matched defaults to RECOVERABLE_TERMINAL so the
     * failure lands in the queue rather than vanishing.
     *
     * @throws FailureClassificationException  Only when the provider code
     *         is unknown (a programming error in adapter registration).
     */
    public function classify(
        string $providerCode,
        string $gatewayOrderId,
        string $observedRawStatus,
        string $failureCode,
    ): FailureClassification {
        if (! in_array($providerCode, ['razorpay', 'paypal'], true)) {
            throw FailureClassificationException::unknownProvider($providerCode);
        }

        $code = strtolower($failureCode);

        // Terminal-class: signature / fraud / hard validation.
        if (in_array($code, ['invalid_signature', 'bad_signature', 'signature_mismatch'], true)) {
            return FailureClassification::TERMINAL_INVALID;
        }
        if (in_array($code, ['fraud_detected', 'risk_threshold_breached'], true)) {
            return FailureClassification::TERMINAL_FRAUD;
        }

        // Transient-class: network, timeout, soft-decline.
        if (in_array($code, [
            'gateway_timeout', 'gateway_unavailable', 'network_error',
            'webhook_timeout', 'rate_limited',
        ], true)) {
            return FailureClassification::RECOVERABLE_TRANSIENT;
        }
        if (in_array($code, [
            'insufficient_funds', 'payment_cancelled', 'auth_declined',
        ], true)) {
            return FailureClassification::RECOVERABLE_TRANSIENT;
        }

        // Default: needs operator attention, but technically recoverable.
        return FailureClassification::RECOVERABLE_TERMINAL;
    }

    /**
     * Retry a recoverable failure by re-running the verification
     * pipeline. Returns Result<Payment> on success, Result::failure
     * when the failure has exceeded its max retry count or remains
     * non-recoverable.
     *
     * The PaymentVerification is produced by the orchestrator after
     * re-running handleWebhook(). This method only governs the
     * bookkeeping (retry counter, nextRetryAt, audit).
     *
     * @return Result<FailureState>
     */
    public function retry(
        FailureState $failure,
        PaymentVerification $verification,
    ): Result {
        if (! $failure->classification()->isRetryable()) {
            return Result::failure(
                sprintf(
                    'failure_not_retryable: classification=%s',
                    $failure->classification()->value,
                ),
            );
        }

        if ($failure->retryCount() >= $failure->maxRetries()) {
            return Result::failure(
                sprintf(
                    'max_retries_exceeded: count=%d max=%d',
                    $failure->retryCount(),
                    $failure->maxRetries(),
                ),
            );
        }

        $payment = $this->payments->findById($failure->paymentId());
        if ($payment === null) {
            return Result::failure('payment_missing: id='.$failure->paymentId()->ulid());
        }

        // The verification pipeline already validated the gateway
        // signature and amount match. We trust its outcome as the
        // new terminal state for the payment. The retry just records
        // that the failure has been observed and resolved by retry.
        try {
            $transitioned = $payment->transitionTo(
                machine: $this->paymentStateMachine,
                to: $verification->status(),
                context: [
                    'amount_minor' => $verification->amountMinor(),
                    'method' => $verification->method(),
                    'gateway_payment_id' => $verification->gatewayPaymentId(),
                    'verified_at' => $verification->verifiedAt()->format(DATE_ATOM),
                ],
            );
            $this->payments->update($transitioned);
        } catch (PaymentStateTransitionException $e) {
            return Result::failure('retry_transition_rejected: '.$e->getMessage());
        }

        $incremented = $this->failureStates->incrementRetry($failure->id());
        $resolved = $this->failureStates->markResolved(
            id: $incremented->id(),
            resolutionNotes: sprintf(
                'Auto-resolved by retry at %s',
                $this->clock->now()->format(DATE_ATOM),
            ),
            resolvedBy: 'system:retry',
            resolvedAt: $this->clock->now(),
        );

        $this->auditLog->append(
            eventType: 'payment.failure.retried',
            entityType: FailureState::ENTITY_TYPE,
            entityId: $resolved->id()->ulid(),
            correlationId: $resolved->correlationId(),
            previousState: $incremented->classification()->value,
            newState: $resolved->classification()->value,
            context: [
                'payment_id' => $resolved->paymentId()->ulid(),
                'retry_count' => $resolved->retryCount(),
                'new_payment_status' => $verification->status()->value,
            ],
            occurredAt: $this->clock->now(),
        );

        return Result::success($resolved);
    }

    /**
     * Mark a failure as resolved without re-running verification.
     * Used by operator actions and by paths that recover outside the
     * gateway (e.g. manual reconciliation).
     *
     * @return Result<FailureState>
     */
    public function markResolved(
        FailureState $failure,
        string $notes,
        ?string $resolvedBy = null,
        ?Identifier $actorId = null,
    ): Result {
        if ($failure->resolvedAt() !== null) {
            return Result::failure('failure_already_resolved: id='.$failure->id()->ulid());
        }

        $resolved = $this->failureStates->markResolved(
            id: $failure->id(),
            resolutionNotes: $notes,
            resolvedBy: $resolvedBy ?? 'operator',
            resolvedAt: $this->clock->now(),
        );

        $this->auditLog->append(
            eventType: 'payment.failure.resolved',
            entityType: FailureState::ENTITY_TYPE,
            entityId: $resolved->id()->ulid(),
            actor: $actorId?->value(),
            correlationId: $resolved->correlationId(),
            previousState: $failure->classification()->value,
            newState: $resolved->classification()->value,
            context: [
                'payment_id' => $resolved->paymentId()->ulid(),
                'resolved_by' => $resolved->resolvedBy(),
                'notes' => $notes,
            ],
            occurredAt: $this->clock->now(),
        );

        return Result::success($resolved);
    }

    /**
     * Append-only helper exposed for the orchestrator to log
     * classification transitions when a transient failure is upgraded
     * to terminal after retries are exhausted. Idempotent on
     * resolved failures.
     *
     * @return Result<FailureState>
     */
    public function escalate(
        FailureState $failure,
        FailureClassification $newClassification,
        string $reason,
    ): Result {
        if ($failure->resolvedAt() !== null) {
            return Result::failure('failure_already_resolved');
        }
        if (! in_array($newClassification, [
            FailureClassification::TERMINAL_INVALID,
            FailureClassification::TERMINAL_FRAUD,
        ], true)) {
            return Result::failure(
                'escalation_target_must_be_terminal: got '.$newClassification->value,
            );
        }

        $nextRetryAt = null;
        $updated = $failure->withChanges([
            'is_retryable' => false,
        ]);

        $this->failureStates->update($updated);

        $this->auditLog->append(
            eventType: 'payment.failure.escalated',
            entityType: FailureState::ENTITY_TYPE,
            entityId: $updated->id()->ulid(),
            correlationId: $updated->correlationId(),
            previousState: $failure->classification()->value,
            newState: $newClassification->value,
            context: [
                'payment_id' => $updated->paymentId()->ulid(),
                'reason' => $reason,
            ],
            occurredAt: $this->clock->now(),
        );

        return Result::success($updated);
    }
}