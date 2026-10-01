<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Contracts\PaymentGatewayContract;
use App\Payments\Contracts\PaymentReconciliationContract;
use App\Payments\Domain\DTOs\VerificationContextDTO;
use App\Payments\Domain\Events\PaymentValidated;
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
 *   1. initialize(DonationIntent) → Donation(pending_payment)
 *      → gateway.initialize() → Payment(INITIALIZED) persisted.
 *   2. handleWebhook(WebhookPayload) → verification pipeline → on
 *      success: Payment.transitionTo(verified status) + Donation.transitionTo(
 *      PAYMENT_VERIFIED) in a single transaction; receipt/email work runs
 *      after commit and only for a successful provider status.
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
     *   1. Select a gateway via PaymentProviderSelector.
     *   2. Call gateway.initialize(PaymentRequest).
     *   3. Persist the Donation (state=PENDING_PAYMENT) and Payment.
     *   4. Create or link a CRM Donor only after verified payment success.
     *   5. Audit + return PaymentResult.
     *
     * Persistence happens inside a single transaction so a gateway
     * failure leaves no orphan Donation row.
     * The active Donation is the funnel record; Donor rows represent
     * converted identified contributions, not abandoned checkout leads.
     *
     * @return Result<PaymentResult>
     *
     * @phpstan-return Result<PaymentResult>|Result<null>
     */
    public function initialize(DonationIntent $intent): Result
    {
        $purpose = $this->purposeFromIntent($intent);
        $donationId = EntityId::generate('donation');

        $paymentIntent = new PaymentIntent(
            donationId: new Identifier($donationId->ulid()),
            donor: $intent->donor(),
            amountMinor: $intent->amountMinor(),
            currency: $intent->currency(),
            purpose: $purpose,
            idempotencyKey: $intent->idempotencyKey() ?? $this->ids->next(),
            candidateProviders: [],
            metadata: [
                'campaign_id' => $intent->campaignId()->value(),
                'donation_id' => $donationId->ulid(),
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
            donorIdentifier: new Identifier($donationId->ulid()),
            amount: $intent->amountMinor(),
            currency: $intent->currency(),
            purpose: $purpose,
            metadata: [
                'campaign_id' => $intent->campaignId()->value(),
                'donation_id' => $donationId->ulid(),
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
            $intent, $donationId, $provider, $paymentRequest,
            $gatewayOrderId, $now, $purpose,
        ): PaymentResult {
            $donation = Donation::draft(
                campaignId: $intent->campaignId(),
                donor: $intent->donor(),
                amountMinor: $intent->amountMinor(),
                currency: $intent->currency(),
                donorId: null,
                dedication: $intent->dedication(),
                donorMessage: $intent->donorMessage(),
                internalNotes: $intent->internalNotes(),
                idempotencyKey: $intent->idempotencyKey(),
                metadata: $intent->metadata(),
                id: $donationId,
            );
            $donation = $donation->transitionTo(
                machine: $this->donationStateMachine,
                to: DonationState::PENDING_PAYMENT,
                context: ['payment_initiated_at' => $now->format(DATE_ATOM)],
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

            $transitioned = $this->transitionVerifiedPayment(
                $local,
                $verification->status(),
                [
                    'amount_minor' => $verification->amountMinor(),
                    'method' => $verification->method(),
                    'gateway_payment_id' => $verification->gatewayPaymentId(),
                    'verified_at' => $verification->verifiedAt()->format(DATE_ATOM),
                ],
            );
            $this->payments->update($transitioned);

            $donation = $this->donations->findById($local->donationId());
            if ($donation !== null) {
                if ($verification->isSuccess()) {
                    $donation = $this->linkDonorAfterConversion($donation);
                    $target = DonationState::PAYMENT_VERIFIED;
                } else {
                    $target = in_array($verification->status(), [
                        TransactionStatus::CANCELLED,
                        TransactionStatus::EXPIRED,
                    ], true)
                        ? DonationState::CANCELLED
                        : DonationState::FAILED;
                }

                $donationTransitioned = $donation->transitionTo(
                    machine: $this->donationStateMachine,
                    to: $target,
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

        if (! $transitioned->status()->isSuccessful()) {
            return Result::success($transitioned);
        }

        // Receipt generation is bound to payment validation: dispatch the
        // domain signal AFTER the verified-payment transaction has
        // committed. ReceiptIssuanceCoordinator fans it out to the
        // dedicated `receipts` queue, so the receipt is produced by a
        // dedicated worker rather than inline in this HTTP path.
        event(new PaymentValidated(
            paymentUlid: $transitioned->id()->ulid(),
            gatewayOrderId: $gatewayOrderId,
            status: $transitioned->status(),
            occurredAt: $this->clock->now(),
        ));

        return Result::success($transitioned);
    }

    /**
     * Synchronous Razorpay Standard Checkout callback verification.
     *
     * Verifies the HMAC signature issued by Razorpay's checkout.js modal,
     * then advances the matching Payment to CAPTURED so the donor sees
     * "Payment received" without waiting for the async webhook.
     *
     * The webhook remains the canonical reconciliation source-of-truth;
     * this path is an early-flip for UX. If the webhook arrives later for
     * the same order, it sees a terminal-success Payment and short-circuits
     * via the Stage 4 idempotency check in PaymentVerificationService.
     *
     * Steps:
     *   1. Verify HMAC-SHA256 signature "{order_id}|{payment_id}" using key_secret.
     *   2. Look up local Payment by gateway_order_id (locked for update).
     *   3. Short-circuit if already in a terminal-success state.
     *   4. Transition Payment → CAPTURED via PaymentStateMachine.
     *   5. Transition Donation → PAYMENT_VERIFIED.
     *   6. Issue receipt (best-effort, same non-fatal policy as webhook path).
     *
     * @return Result<TransactionStatus>
     *
     * @phpstan-return Result<TransactionStatus>|Result<null>
     */
    public function verifyCheckoutCallback(
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $razorpaySignature,
    ): Result {
        // Step 1: HMAC signature verification (pure, no DB).
        // Signature = HMAC-SHA256(key_secret, "{order_id}|{payment_id}").
        $keySecret = (string) config('payments.providers.razorpay.key_secret', '');
        if ($keySecret === '') {
            return Result::failure('verify_signature_missing_key_secret');
        }

        $expected = hash_hmac('sha256', $razorpayOrderId.'|'.$razorpayPaymentId, $keySecret);
        if (! hash_equals($expected, $razorpaySignature)) {
            return Result::failure('verify_signature_mismatch');
        }

        // The gateway signed this callback, so the capture is
        // authoritative. Commit it against local state through the shared
        // state-machine path (idempotency, donation mirror, receipt).
        return $this->commitGatewayStatus(
            $razorpayOrderId,
            TransactionStatus::CAPTURED,
            [
                'method' => 'checkout_callback',
                'gateway_payment_id' => $razorpayPaymentId,
            ],
        );
    }

    /**
     * Reconcile a local Payment with the gateway's authoritative order
     * status.
     *
     * Backend safety net for when the async webhook is delayed,
     * misconfigured, or never delivered (and the donor closed the tab
     * before the synchronous checkout callback fired). It closes the gap
     * where the donor has really paid but the local Payment row is still
     * non-terminal, which would otherwise make the completion page report
     * a payment as unsuccessful.
     *
     * Only definitive outcomes are persisted; an order still awaiting
     * payment returns the unchanged local status.
     *
     * @return Result<TransactionStatus>
     *
     * @phpstan-return Result<TransactionStatus>|Result<null>
     */
    public function reconcileOrder(string $gatewayOrderId): Result
    {
        $payment = $this->payments->findByGatewayOrderId($gatewayOrderId);
        if ($payment === null) {
            return Result::failure('reconcile_unknown_order: '.$gatewayOrderId);
        }

        if ($payment->status()->isTerminal() || $payment->status()->isSuccessful()) {
            return Result::success($payment->status());
        }

        $gateway = $this->resolveGatewayFor($payment);
        if ($gateway->isFailure()) {
            return $gateway;
        }

        $adapter = $gateway->value();
        if (! $adapter instanceof PaymentReconciliationContract) {
            // Provider cannot report order truth — leave local state alone.
            return Result::success($payment->status());
        }

        $recon = $adapter->reconcileOrder($gatewayOrderId);
        if ($recon->isFailure()) {
            return Result::failure('reconcile_fetch_failed: '.$recon->error());
        }

        $result = $recon->value();

        $definitive = in_array($result->status, [
            TransactionStatus::CAPTURED,
            TransactionStatus::FAILED,
            TransactionStatus::EXPIRED,
            TransactionStatus::CANCELLED,
        ], true);

        if (! $definitive) {
            return Result::success($payment->status());
        }

        $context = [
            'method' => $result->method,
            'gateway_payment_id' => $result->gatewayPaymentId,
        ];
        if ($result->amountMinor > 0) {
            $context['amount_minor'] = $result->amountMinor;
        }

        return $this->commitGatewayStatus($gatewayOrderId, $result->status, $context);
    }

    /**
     * Converge a local Payment to a gateway-verified status.
     *
     * Shared by the synchronous checkout callback and reconciliation.
     * Locks the Payment row, applies the PaymentStateMachine transition,
     * mirrors the Donation to its matching outcome state
     * (PAYMENT_VERIFIED on success; FAILED/CANCELLED otherwise), and
     * persists — all inside one transaction. Best-effort receipt issuance
     * follows after commit.
     *
     * @param  array<string, mixed>  $context  Extra FSM context (method,
     *                                          gateway_payment_id, amount_minor, …)
     *
     * @return Result<TransactionStatus>
     *
     * @phpstan-return Result<TransactionStatus>|Result<null>
     */
    private function commitGatewayStatus(
        string $gatewayOrderId,
        TransactionStatus $targetStatus,
        array $context = [],
    ): Result {
        $commit = $this->coordinator->execute(function () use (
            $gatewayOrderId, $targetStatus, $context,
        ): Result {
            $local = $this->payments->lockByGatewayOrderIdForUpdate($gatewayOrderId);
            if ($local === null) {
                return Result::failure(
                    'verify_unknown_order: no local Payment with gateway_order_id='.$gatewayOrderId,
                );
            }

            // Idempotency — already terminal-success? Return as-is.
            if ($local->status()->isSuccessful()) {
                return Result::success($local->status());
            }

            $context['amount_minor'] = $context['amount_minor'] ?? $local->amountMinor();
            $context['verified_at'] = $context['verified_at'] ?? $this->clock->now()->format(DATE_ATOM);

            $transitioned = $this->transitionVerifiedPayment($local, $targetStatus, $context);
            $this->payments->update($transitioned);

            $donation = $this->donations->findById($local->donationId());
            if ($donation !== null) {
                if ($targetStatus->isSuccessful()) {
                    $donation = $this->linkDonorAfterConversion($donation);
                    $target = DonationState::PAYMENT_VERIFIED;
                } else {
                    $target = in_array($targetStatus, [
                        TransactionStatus::CANCELLED,
                        TransactionStatus::EXPIRED,
                    ], true)
                        ? DonationState::CANCELLED
                        : DonationState::FAILED;
                }

                $donationTransitioned = $donation->transitionTo(
                    machine: $this->donationStateMachine,
                    to: $target,
                    context: [
                        'verified_at' => $this->clock->now()->format(DATE_ATOM),
                    ],
                );
                $this->donations->update($donationTransitioned);
            }

            return Result::success($transitioned->status());
        });

        if ($commit->isFailure()) {
            return $commit;
        }

        $committed = $commit->value();

        // Receipt generation is bound to payment validation: dispatch the
        // domain signal AFTER the capture transaction has committed.
        // ReceiptIssuanceCoordinator fans it out to the dedicated
        // `receipts` queue. No inline best-effort issuance here.
        if ($committed->isSuccessful()) {
            $payment = $this->payments->findByGatewayOrderId($gatewayOrderId);
            if ($payment !== null) {
                event(new PaymentValidated(
                    paymentUlid: $payment->id()->ulid(),
                    gatewayOrderId: $gatewayOrderId,
                    status: $committed,
                    occurredAt: $this->clock->now(),
                ));
            }
        }

        return Result::success($committed);
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
     * Apply a provider-verified payment status using the canonical state
     * machine. Provider webhooks may confirm capture directly from an
     * initialized/pending checkout, so the state machine owns that path.
     *
     * @param array<string, mixed> $context
     */
    private function transitionVerifiedPayment(
        Payment $payment,
        TransactionStatus $status,
        array $context,
    ): Payment {
        return $payment->transitionTo(
            machine: $this->paymentStateMachine,
            to: $status,
            context: $context,
        );
    }

    /**
     * Link or create a Donor row only once the contribution has converted.
     * Donation snapshots continue to support receipt generation and audit.
     */
    private function linkDonorAfterConversion(Donation $donation): Donation
    {
        if ($donation->donorId() !== null || $donation->isAnonymousFlag()) {
            return $donation;
        }

        $identity = DonorIdentity::identified(
            name: (string) $donation->donorNameSnapshot(),
            email: $donation->donorEmailSnapshot(),
            phone: $donation->donorPhoneSnapshot(),
            pan: $donation->donorPanSnapshot(),
            address: $donation->donorAddressSnapshot(),
        );
        $resolved = $this->resolveDonor($identity);
        if ($resolved->isFailure()) {
            throw new \RuntimeException((string) $resolved->error());
        }

        $donor = $resolved->value();
        return $donor === null
            ? $donation
            : $donation->withChanges(['donor_id' => $donor->id()]);
    }

    /**
     * Find-or-create a Donor row for a verified identified contribution.
     * Anonymous donations remain unlinked.
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
