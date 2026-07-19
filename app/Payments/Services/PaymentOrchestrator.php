<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Domain\DTOs\VerificationContextDTO;
use App\Payments\Domain\Entities\Donation;
use App\Payments\Domain\Entities\Donor;
use App\Payments\Domain\Entities\Payment;
use App\Payments\Domain\Enums\DonationState;
use App\Payments\Domain\Enums\PaymentProvider;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\Exceptions\PaymentInitializationFailedException;
use App\Payments\Domain\Exceptions\RefundExceededException;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\DonorRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\StateMachines\DonationStateMachine;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
use App\Payments\Domain\StateMachines\StateTransitionEvent;
use App\Payments\Domain\ValueObjects\DonationIntent;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Domain\ValueObjects\PaymentIntent;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Payments\Domain\ValueObjects\PaymentResult;
use App\Payments\Domain\ValueObjects\WebhookPayload;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\Support\Clock;
use App\Shared\Support\IdentifierGenerator;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;
use DateTimeImmutable;

/**
 * The conductor of the Financial Kernel.
 *
 * Non-final so unit tests can substitute a recording stub via
 * inheritance; production code resolves through DI and never sees
 * a subclass.
 *
 * Owns the canonical payment workflow:
 *   1. initialize(DonationIntent) → Donation(draft) → gateway.initialize()
 *      → Payment(INITIALIZED) persisted in a single transaction.
 *   2. handleWebhook(WebhookPayload) → verification pipeline → on
 *      success: Payment.transitionTo(verified status) + Donation.transitionTo(
 *      PAYMENT_VERIFIED) + ReceiptService.issue() in a single transaction.
 *      On failure: FailureStateService.record().
 *   3. refund(Identifier, int) → load → ceiling check → gateway.refund
 *      → Payment.transitionTo(REFUNDED|PARTIALLY_REFUNDED).
 *   4. getStatus(Identifier) → terminal: local. non-terminal +
 *      recent verification: local. else: gateway.verify + reconcile.
 *
 * Every state-changing path goes through TransactionCoordinator.
 * Every state transition is delegated to the entity's machine.
 * The orchestrator NEVER calls `new Payment(...)` / `new Donation(...)`
 * directly — those flow through the entity factories or repository
 * load methods.
 *
 * Service-layer rule: this class throws NO business exceptions.
 * Every operation returns Result<...>. Callers (controllers in
 * Pass 2+) decide how to surface a failure to the operator or donor.
 */
class PaymentOrchestrator
{
    public function __construct(
        private readonly ReceiptService $receiptService,
        private readonly FailureStateService $failureStateService,
        private readonly PaymentProviderSelector $selector,
        private readonly PaymentVerificationService $verification,
        private readonly TransactionCoordinator $coordinator,
        private readonly AuditEventRepositoryContract $auditLog,
        private readonly Clock $clock,
        private readonly IdentifierGenerator $ids,
        private readonly PaymentRepositoryContract $payments,
        private readonly DonationRepositoryContract $donations,
        private readonly DonorRepositoryContract $donors,
        private readonly PaymentStateMachine $paymentStateMachine,
        private readonly DonationStateMachine $donationStateMachine,
    ) {}

    /**
     * Initialize a payment for a verified donation intent.
     *
     * Steps:
     *   1. Find-or-create the Donor from the DonationIntent's identity.
     *   2. Select a gateway via PaymentProviderSelector.
     *   3. Persist the Donation (state=DRAFT).
     *   4. Call gateway.initialize(PaymentRequest).
     *   5. Persist the Payment (status=INITIALIZED) referencing the
     *      Donation and gateway order_id.
     *   6. Audit + return PaymentResult.
     *
     * Steps 3-5 happen inside a single transaction so a gateway
     * failure leaves no orphan Donation row.
     *
     * @phpstan-return Result<PaymentResult>|Result<Donor|null>|Result<null>
     * @return Result<PaymentResult>
     */
    public function initialize(DonationIntent $intent): Result
    {
        $donorResolution = $this->resolveDonor($intent->donor());
        if ($donorResolution->isFailure()) {
            return $donorResolution;
        }
        $donorEntity = $donorResolution->value();
        $donorId = $donorEntity?->id();
        $purpose = $this->purposeFromIntent($intent);

        $paymentIntent = new PaymentIntent(
            donationId: new Identifier($this->ids->next()),
            donor: $intent->donor(),
            amountMinor: $intent->amountMinor(),
            currency: $intent->currency(),
            purpose: $purpose,
            idempotencyKey: $intent->idempotencyKey() ?? $this->ids->next(),
            candidateProviders: [],
            metadata: [
                'campaign_id' => $intent->campaignId()->value(),
                'donor_id' => $donorId?->ulid() ?? '',
            ],
        );

        $selection = $this->selector->select($paymentIntent);
        $providerCode = $selection->providerName();
        $provider = PaymentProvider::tryFrom($providerCode);
        if ($provider === null) {
            return Result::failure(sprintf(
                'unknown_provider_code: [%s]',
                $providerCode,
            ));
        }

        $paymentRequest = new PaymentRequest(
            donorIdentifier: new Identifier($donorId?->ulid() ?? $this->ids->next()),
            amount: $intent->amountMinor(),
            currency: $intent->currency(),
            purpose: $purpose,
            metadata: [
                'campaign_id' => $intent->campaignId()->value(),
                'donation_idempotency_key' => $intent->idempotencyKey() ?? '',
            ],
            idempotencyKey: $paymentIntent->idempotencyKey(),
        );

        $gatewayResult = $selection->initialize($paymentRequest);
        if ($gatewayResult->isFailure()) {
            return Result::failure(
                'gateway_initialize_failed: '.$gatewayResult->error(),
            );
        }

        $gatewayPayload = $gatewayResult->value();
        $gatewayOrderId = (string) $gatewayPayload['order_id'];
        if ($gatewayOrderId === '') {
            return Result::failure('gateway_response_missing_order_id');
        }

        $donationIdUlid = $this->ids->next();
        $paymentIdUlid = $this->ids->next();
        $now = $this->clock->now();

        $persist = $this->coordinator->execute(function () use (
            $intent, $donorId, $provider, $paymentRequest,
            $gatewayOrderId, $donationIdUlid, $paymentIdUlid, $now, $purpose,
        ): PaymentResult {
            $donation = Donation::draft(
                campaignId: EntityId::fromString($intent->campaignId()->value()),
                donor: $intent->donor(),
                amountMinor: $intent->amountMinor(),
                currency: $intent->currency(),
                donorId: $donorId,
                dedication: $intent->dedication(),
                donorMessage: $intent->donorMessage(),
                internalNotes: $intent->internalNotes(),
                idempotencyKey: $intent->idempotencyKey(),
                metadata: $intent->metadata(),
                id: EntityId::fromString($donationIdUlid),
            );
            $this->donations->save($donation);

            $payment = Payment::initialize(
                donationId: $donation->id(),
                providerCode: $provider,
                amountMinor: $intent->amountMinor(),
                currency: $intent->currency(),
                idempotencyKey: $paymentRequest->idempotencyKey() ?? $this->ids->next(),
                metadata: [
                    'purpose' => $purpose,
                    'gateway_order_id' => $gatewayOrderId,
                ],
                id: EntityId::fromString($paymentIdUlid),
            );
            $this->payments->save($payment);

            $this->auditLog->append(
                eventType: 'payment.initialize',
                entityType: Payment::ENTITY_TYPE,
                entityId: $payment->id()->ulid(),
                correlationId: $intent->idempotencyKey(),
                previousState: null,
                newState: TransactionStatus::INITIALIZED->value,
                context: [
                    'donation_id' => $donation->id()->ulid(),
                    'provider' => $provider->value,
                    'amount_minor' => $intent->amountMinor(),
                    'currency' => $intent->currency()->value,
                    'gateway_order_id' => $gatewayOrderId,
                ],
                occurredAt: $now,
            );

            return new PaymentResult(
                provider: $provider,
                gatewayOrderId: $gatewayOrderId,
                amountMinor: $intent->amountMinor(),
                currency: $intent->currency(),
                status: TransactionStatus::INITIALIZED,
                rawResponse: [],
                checkoutUrl: null,
            );
        });

        return $persist;
    }

    /**
     * @phpstan-return Result<Payment>|Result<null>
     * @return Result<Payment>
     */
    public function handleWebhook(WebhookPayload $payload): Result
    {
        // Doctrine: the orchestrator is the bridge between a raw webhook
        // (transport-layer) and the PaymentStateMachine (regional authority).
        // It OWNS the local Payment/Donation lookup so callers (controllers,
        // queues) only need to supply the gateway_order_id. This makes the
        // controller thin (pure transport) and centralizes the lookup here.
        //
        // Pre-Pass-1.4 this code required the caller to surface all five
        // expected fields via metadata. Pass 1.4+ uses the
        // PaymentRepository fast-path by gateway_order_id so the lookup is
        // canonical and the controller never needs to touch the repository.
        $metadata = $payload->metadata();
        $gatewayOrderId = trim((string) ($metadata['gateway_order_id'] ?? ''));

        if ($gatewayOrderId === '') {
            return Result::failure(
                'webhook_metadata_insufficient: gateway_order_id is required',
            );
        }

        $local = $this->payments->findByGatewayOrderId($gatewayOrderId);
        if ($local === null) {
            return Result::failure(
                'webhook_unknown_order: no local Payment with gateway_order_id='.$gatewayOrderId,
            );
        }

        $expectedKey = (string) ($local->idempotencyKey() ?? '');
        if ($expectedKey === '') {
            // Doctrine: every Payment row carries an idempotency key. Missing
            // key indicates a row pre-dating Pass 1.5; do not trust it.
            return Result::failure(
                'webhook_payment_missing_idempotency_key: gateway_order_id='.$gatewayOrderId,
            );
        }

        $context = new VerificationContextDTO(
            payload: $payload,
            donationId: new Identifier($local->donationId()->ulid()),
            paymentId: new Identifier($local->id()->ulid()),
            expectedAmountMinor: $local->amountMinor(),
            expectedCurrency: $local->currency(),
            expectedIdempotencyKey: $expectedKey,
        );

        $verified = $this->verification->verify($context);
        if ($verified->isFailure()) {
            $this->failureStateService->record(
                paymentId: $local->id(),
                providerCode: $payload->provider()->value,
                gatewayOrderId: $verified->error() ?? 'unknown',
                observedStatus: TransactionStatus::FAILED,
                failureCode: 'webhook_verification_failed',
                failureReason: $verified->error(),
                classification: null,
                metadata: [
                    'stage_results' => $context->stageResults(),
                ],
                context: [
                    'correlation_id' => $payload->providerEventId() ?: null,
                ],
            );
            return Result::failure(
                'verification_failed: '.$verified->error(),
            );
        }

        $verification = $verified->value();

        $commit = $this->coordinator->execute(function () use (
            $verification, $local,
        ): Payment {
            $transitioned = $local->transitionTo(
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

            $donation = $this->donations->findById($local->donationId());
            if ($donation !== null) {
                $donationTransitioned = $donation->transitionTo(
                    machine: $this->donationStateMachine,
                    to: DonationState::PAYMENT_VERIFIED,
                    context: [
                        'verified_at' => $verification->verifiedAt()->format(DATE_ATOM),
                    ],
                );
                $this->donations->update($donationTransitioned);
            }

            return $transitioned;
        });

        if ($commit->isFailure()) {
            return $commit;
        }

        // Receipt issuance is intentionally outside the verification
        // transaction so a receipt failure does not roll back the
        // verified payment. The receipt service escalates a
        // FailureState on its own failure path.
        $receiptResult = $this->receiptService->issue(
            new Identifier($local->id()->ulid()),
        );

        if ($receiptResult->isFailure()) {
            // The receipt failure path is non-fatal; the payment
            // itself is verified. We surface a warning Result but
            // still return the Payment as the success payload.
            $this->auditLog->append(
                eventType: 'payment.receipt.deferred',
                entityType: Payment::ENTITY_TYPE,
                entityId: $local->id()->ulid(),
                previousState: $commit->value()->status()->value,
                newState: $commit->value()->status()->value,
                context: [
                    'receipt_error' => $receiptResult->error(),
                ],
                occurredAt: $this->clock->now(),
            );
        }

        /** @var Result<Payment> $commit */
        $commit = Result::success($commit->value());

        return $commit;
    }

    /**
     * Refund a payment in full or partially.
     *
     * @phpstan-return Result<Payment>|Result<PaymentGatewayContract>|Result<null>
     * @return Result<Payment>
     */
    public function refund(Identifier $transactionId, int $amountMinor): Result
    {
        if ($amountMinor <= 0) {
            return Result::failure(
                'refund_amount_must_be_positive: got '.$amountMinor,
            );
        }

        $payment = $this->payments->findById(
            new EntityId('payment', $transactionId->value()),
        );
        if ($payment === null) {
            return Result::failure(
                'payment_not_found: '.$transactionId->value(),
            );
        }

        $captured = $payment->amountCapturedMinor() ?? 0;
        $alreadyRefunded = $payment->amountRefundedMinor();
        if ($captured - $alreadyRefunded < $amountMinor) {
            throw RefundExceededException::exceedsCaptured(
                $payment->id()->ulid(),
                $captured,
                $alreadyRefunded,
                $amountMinor,
            );
        }

        $isFull = ($amountMinor === $captured - $alreadyRefunded);
        $targetStatus = $isFull
            ? TransactionStatus::REFUNDED
            : TransactionStatus::PARTIALLY_REFUNDED;

        // SM validation FIRST — reject invalid states before touching the gateway.
        // Refunds are out of scope for Pass 1.3; this guard ensures that if
        // a refund request arrives for an invalid state, we fail cleanly without
        // making an irreversible external gateway call.
        $transitioned = $payment->transitionTo(
            machine: $this->paymentStateMachine,
            to: $targetStatus,
            context: [
                'amount_minor' => $payment->amountMinor(),
                'amount_refunded_minor' => $alreadyRefunded + $amountMinor,
            ],
        );

        $gateway = $this->resolveGatewayFor($payment);
        if ($gateway->isFailure()) {
            return $gateway;
        }

        $refundResult = $gateway->value()->refund($transactionId, $amountMinor);
        if ($refundResult->isFailure()) {
            // Gateway failed — SM state was never persisted (transitioned is
            // a new object; $this->payments->update was never called). No
            // compensating action needed; the local state is unchanged.
            return Result::failure(
                'gateway_refund_failed: '.$refundResult->error(),
            );
        }

        $this->payments->update($transitioned);

        $this->auditLog->append(
            eventType: 'payment.refund',
            entityType: Payment::ENTITY_TYPE,
            entityId: $payment->id()->ulid(),
            previousState: $payment->status()->value,
            newState: $targetStatus->value,
            context: [
                'amount_refunded_minor' => $amountMinor,
                'is_full_refund' => $isFull,
            ],
            occurredAt: $this->clock->now(),
        );

        return Result::success($transitioned);
    }

    /**
     * @phpstan-return Result<TransactionStatus>|Result<PaymentGatewayContract>|Result<null>
     * @return Result<TransactionStatus>
     */
    public function getStatus(Identifier $transactionId): Result
    {
        $payment = $this->payments->findById(
            new EntityId('payment', $transactionId->value()),
        );
        if ($payment === null) {
            return Result::failure(
                'payment_not_found: '.$transactionId->value(),
            );
        }

        if ($payment->status()->isTerminal()) {
            return Result::success($payment->status());
        }

        $gateway = $this->resolveGatewayFor($payment);
        if ($gateway->isFailure()) {
            return $gateway;
        }

        $verified = $gateway->value()->verify($payment->providerOrderId() ?? '');
        if ($verified->isFailure()) {
            return Result::failure('gateway_verify_failed: '.$verified->error());
        }

        return Result::success($verified->value());
    }

    /**
     * Find-or-create a Donor row from the DonationIntent's identity.
     *
     * Returns null for anonymous donors (the schema allows
     * donations.donor_id IS NULL). Returns the persisted Donor
     * entity for identified donors.
     *
     * @phpstan-return Result<Donor>|Result<null>
     * @return Result<Donor|null>
     */
    private function resolveDonor(DonorIdentity $identity): Result
    {
        if ($identity->isAnonymous()) {
            return Result::success(null);
        }

        $existing = $this->donors->findByEmailOrPhone(
            $identity->email(),
            $identity->phone(),
        );
        if ($existing !== null) {
            return Result::success($existing);
        }

        $name = (string) ($identity->name() ?? '');
        if (trim($name) === '') {
            return Result::failure('donor_name_required_for_identified_donor');
        }

        $id = EntityId::fromString($this->ids->next());
        $donor = Donor::identified(
            name: $name,
            email: $identity->email(),
            phone: $identity->phone(),
            panNumber: $identity->pan(),
            address: $identity->address(),
            id: $id,
        );
        $this->donors->save($donor);

        return Result::success($donor);
    }

    /**
     * Derive a human-readable purpose string from the DonationIntent.
     * Anonymous donations use a generic label; identified donations
     * may carry a dedication which is preferred when present.
     */
    private function purposeFromIntent(DonationIntent $intent): string
    {
        if ($intent->hasDedication()) {
            return 'Temple donation — '.$intent->dedication();
        }

        return 'Temple donation';
    }

    /**
     * Resolve the PaymentGatewayContract for a payment's provider.
     *
     * @phpstan-return Result<PaymentGatewayContract>|Result<null>
     * @return Result<PaymentGatewayContract>
     */
    private function resolveGatewayFor(Payment $payment): Result
    {
        $provider = $payment->providerCode();
        $intent = new PaymentIntent(
            donationId: new Identifier($payment->donationId()->ulid()),
            donor: new DonorIdentity(),
            amountMinor: $payment->amountMinor(),
            currency: $payment->currency(),
            purpose: 'refund',
            idempotencyKey: $this->ids->next(),
            candidateProviders: [$provider],
            metadata: [],
        );

        try {
            return Result::success($this->selector->select($intent));
        } catch (\App\Payments\Domain\Exceptions\GatewaySelectionException $e) {
            return Result::failure('gateway_resolve_failed: '.$e->getMessage());
        }
    }
}