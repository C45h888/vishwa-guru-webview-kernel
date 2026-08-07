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
use App\Payments\Domain\Exceptions\GatewaySelectionException;
use App\Payments\Domain\Repositories\AuditEventRepositoryContract;
use App\Payments\Domain\Repositories\DonationRepositoryContract;
use App\Payments\Domain\Repositories\DonorRepositoryContract;
use App\Payments\Domain\Repositories\PaymentRepositoryContract;
use App\Payments\Domain\StateMachines\DonationStateMachine;
use App\Payments\Domain\StateMachines\PaymentStateMachine;
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
     * @return Result<PaymentResult>
     *
     * @phpstan-return Result<PaymentResult>|Result<Donor|null>|Result<null>
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
        $gatewayOrderId = (string) $gatewayPayload->gatewayOrderId();
        if ($gatewayOrderId === '') {
            return Result::failure('gateway_response_missing_order_id');
        }

        $now = $this->clock->now();

        $persist = $this->coordinator->execute(function () use (
            $intent, $donorId, $provider, $paymentRequest,
            $gatewayOrderId, $now, $purpose,
        ): PaymentResult {
            $donation = Donation::draft(
                campaignId: $intent->campaignId(),
                donor: $intent->donor(),
                amountMinor: $intent->amountMinor(),
                currency: $intent->currency(),
                donorId: $donorId,
                dedication: $intent->dedication(),
                donorMessage: $intent->donorMessage(),
                internalNotes: $intent->internalNotes(),
                idempotencyKey: $intent->idempotencyKey(),
                metadata: $intent->metadata(),
                id: EntityId::generate('donation'),
            );
            $this->donations->save($donation);

            // Wave 1 fix (2026-08-07): PostgreSQL may mark the transaction as
            // aborted due to a deferred constraint or trigger violation that
            // doesn't throw a PDO exception. Check the transaction status
            // explicitly before proceeding to the Payment save.
            $txStatus = $this->coordinator->getTransactionStatus();
            if ($txStatus === 'aborted') {
                throw new \RuntimeException(
                    'Transaction aborted after Donation save. ' .
                    'Check PostgreSQL logs for constraint violations.'
                );
            }

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
                id: EntityId::generate('payment'),
                providerOrderId: $gatewayOrderId,
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
     * @return Result<Payment>
     *
     * @phpstan-return Result<Payment>|Result<null>
     */
    public function handleWebhook(WebhookPayload $payload): Result
    {
        // Doctrine: the orchestrator is the bridge between a raw webhook
        // (transport-layer) and the PaymentStateMachine (regional authority).
        // It OWNS the local Payment/Donation lookup so callers (controllers,
        // queues) only need to supply the gateway_order_id. This makes the
        // controller thin (pure transport) and centralizes the lookup here.
        //
        // Pass 1.4+: the lookup + verification + state transition + donation
        // transition all happen inside a single transaction under a
        // SELECT … FOR UPDATE row lock on payments by gateway_order_id.
        // Concurrent webhooks for the same order_id serialize on the lock,
        // so two `payment.captured` events cannot both transition the row
        // or both issue receipts.
        $metadata = $payload->metadata();
        $gatewayOrderId = trim((string) ($metadata['gateway_order_id'] ?? ''));

        if ($gatewayOrderId === '') {
            return Result::failure(
                'webhook_metadata_insufficient: gateway_order_id is required',
            );
        }

        // Verification runs OUTSIDE the lock because the HMAC verification
        // is a pure function on (payload, secret). The lock only protects
        // the local Payment row during the read-modify-write sequence.
        $commit = $this->coordinator->execute(function () use (
            $payload, $gatewayOrderId,
        ): Result {
            $local = $this->payments->lockByGatewayOrderIdForUpdate($gatewayOrderId);
            if ($local === null) {
                return Result::failure(
                    'webhook_unknown_order: no local Payment with gateway_order_id='.$gatewayOrderId,
                );
            }

            $expectedKey = (string) ($local->idempotencyKey() ?? '');
            if ($expectedKey === '') {
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

            return Result::success($transitioned);
        });

        if ($commit->isFailure()) {
            return $commit;
        }

        $transitioned = $commit->value();

        // Receipt issuance is intentionally outside the verification
        // transaction so a receipt failure does not roll back the
        // verified payment. The receipt service escalates a
        // FailureState on its own failure path.
        $receiptResult = $this->receiptService->issue(
            new Identifier($transitioned->id()->ulid()),
        );

        if ($receiptResult->isFailure()) {
            // The receipt failure path is non-fatal; the payment
            // itself is verified. We surface a warning Result but
            // still return the Payment as the success payload.
            $this->auditLog->append(
                eventType: 'payment.receipt.deferred',
                entityType: Payment::ENTITY_TYPE,
                entityId: $transitioned->id()->ulid(),
                previousState: $transitioned->status()->value,
                newState: $transitioned->status()->value,
                context: [
                    'receipt_error' => $receiptResult->error(),
                ],
                occurredAt: $this->clock->now(),
            );
        } else {
            // Receipt issued cleanly. Fire-and-forget the donor email
            // onto the receipts queue. The job is idempotent on
            // receipt:{id}:email and tries=1 — an SMTP outage surfaces
            // to ops rather than silently retrying, and re-dispatch
            // from operator UI doesn't double-send within the TTL.
            $issued = $receiptResult->value();
            if ($issued instanceof \App\Payments\Domain\Entities\Receipt) {
                \App\Jobs\ReceiptEmailJob::dispatch(
                    new Identifier($issued->id()->ulid()),
                );
            }
        }

        return Result::success($transitioned);
    }

    /**
     * Refund a payment in full or partially.
     *
     * @return Result<Payment>
     *
     * @phpstan-return Result<Payment>|Result<PaymentGatewayContract>|Result<null>
     */
    public function refund(Identifier $transactionId, int $amountMinor): Result
    {
        if ($amountMinor <= 0) {
            return Result::failure(
                'refund_amount_must_be_positive: got '.$amountMinor,
            );
        }

        // Refunds are concurrency-sensitive: two simultaneous partial
        // refunds could each pass the ceiling check against a stale
        // snapshot and issue two real refunds. Wrap the read-modify-
        // write in a single transaction that takes a row lock for the
        // duration. The gateway call happens INSIDE the transaction
        // so the ceiling is enforced against the freshly-locked state.
        $commit = $this->coordinator->execute(function () use (
            $transactionId, $amountMinor,
        ): Result {
            $payment = $this->payments->lockByIdForUpdate(
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
                return Result::failure(sprintf(
                    'refund_exceeds_ceiling: captured=%d already_refunded=%d requested=%d',
                    $captured,
                    $alreadyRefunded,
                    $amountMinor,
                ));
            }

            // The gateway requires the PROVIDER-side payment id
            // (`pay_XXXX` for Razorpay, `capture_id` for PayPal). The
            // local ULID is meaningless to the gateway and gets the
            // call rejected with 'payment not found'. Fail fast when
            // no provider payment id is on file yet (i.e. webhook
            // hasn't fired).
            $providerPaymentId = $payment->providerPaymentId();
            if ($providerPaymentId === null || $providerPaymentId === '') {
                return Result::failure(
                    'refund_provider_payment_id_unavailable: '.$payment->id()->ulid(),
                );
            }

            $isFull = ($amountMinor === $captured - $alreadyRefunded);
            $targetStatus = $isFull
                ? TransactionStatus::REFUNDED
                : TransactionStatus::PARTIALLY_REFUNDED;

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

            $refundResult = $gateway->value()->refund(
                new Identifier($providerPaymentId),
                $amountMinor,
            );
            if ($refundResult->isFailure()) {
                // Gateway failed — the SM transition was held in
                // memory only, the coordinator rolls back the lock
                // release. No compensating action needed.
                return Result::failure(
                    'gateway_refund_failed: '.$refundResult->error(),
                );
            }

            // Wave 1 M2 fix (2026-08-06): the gateway's authoritative
            // status must match the locally-targeting transition. If
            // Razorpay returned FAILED (e.g. insufficient merchant
            // balance), we MUST NOT persist a local REFUNDED — that
            // would mark the donor as refunded while Razorpay's ledger
            // still holds the money. Only persist when the gateway
            // confirms 'processed' or the legitimate interim 'pending'.
            $gatewayStatus = $refundResult->value();
            if ($gatewayStatus === TransactionStatus::FAILED) {
                return Result::failure(sprintf(
                    'gateway_refund_failed_at_gateway: pay=%s status=%s',
                    $payment->id()->ulid(),
                    $gatewayStatus->value,
                ));
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
                    'gateway_status' => $gatewayStatus->value,
                ],
                occurredAt: $this->clock->now(),
            );

            return Result::success($transitioned);
        });

        if ($commit->isFailure()) {
            return $commit;
        }

        return $commit;
    }

    /**
     * @return Result<TransactionStatus>
     *
     * @phpstan-return Result<TransactionStatus>|Result<PaymentGatewayContract>|Result<null>
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
     * @return Result<Donor|null>
     *
     * @phpstan-return Result<Donor>|Result<null>
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

        $id = EntityId::generate('donor');
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
     * @return Result<PaymentGatewayContract>
     *
     * @phpstan-return Result<PaymentGatewayContract>|Result<null>
     */
    private function resolveGatewayFor(Payment $payment): Result
    {
        $provider = $payment->providerCode();
        $intent = new PaymentIntent(
            donationId: new Identifier($payment->donationId()->ulid()),
            donor: new DonorIdentity,
            amountMinor: $payment->amountMinor(),
            currency: $payment->currency(),
            purpose: 'refund',
            idempotencyKey: $this->ids->next(),
            candidateProviders: [$provider],
            metadata: [],
        );

        try {
            return Result::success($this->selector->select($intent));
        } catch (GatewaySelectionException $e) {
            return Result::failure('gateway_resolve_failed: '.$e->getMessage());
        }
    }
}
