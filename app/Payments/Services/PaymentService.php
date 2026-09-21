<?php

declare(strict_types=1);

namespace App\Payments\Services;

use App\Payments\Domain\ValueObjects\DonationIntent;
use App\Payments\Domain\ValueObjects\WebhookPayload;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Stable, public API surface for the Payments domain.
 *
 * Non-final so unit tests can substitute a recording stub via
 * inheritance; production code resolves through DI and never sees
 * a subclass.
 *
 * Other domains (Donations, Admin, future Console) import
 * PaymentService instead of the PaymentOrchestrator directly. This
 * indirection lets us refactor the orchestrator's internal
 * collaboration graph without breaking downstream imports.
 *
 * Every method is a 1-line delegation to the orchestrator. The
 * service exists to define the contract surface and to attach the
 * Shared infrastructure dependencies (Clock, IdentifierGenerator,
 * AuditEventRepositoryContract) that callers don't need to think
 * about — those flow through to the orchestrator transparently.
 */
final class PaymentService
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
    ) {}

    /**
     * @return Result<\App\Payments\Domain\ValueObjects\PaymentResult>
     */
    public function initialize(DonationIntent $intent): Result
    {
        return $this->orchestrator->initialize($intent);
    }

    /**
     * @return Result<\App\Payments\Domain\Entities\Payment>
     */
    public function handleWebhook(WebhookPayload $payload): Result
    {
        return $this->orchestrator->handleWebhook($payload);
    }

    /**
     * @return Result<\App\Payments\Domain\Entities\Payment>
     */
    public function refund(Identifier $transactionId, int $amountMinor): Result
    {
        return $this->orchestrator->refund($transactionId, $amountMinor);
    }

    /**
     * @return Result<\App\Payments\Domain\Enums\TransactionStatus>
     */
    public function getStatus(Identifier $transactionId): Result
    {
        return $this->orchestrator->getStatus($transactionId);
    }

    /**
     * Synchronous Razorpay Standard Checkout callback verification.
     *
     * Verifies the HMAC-SHA256 signature Razorpay issues in the modal's
     * `handler` response (signed with key_secret over "{order_id}|{payment_id}"),
     * then immediately advances the matching Payment to CAPTURED so the
     * donor sees the success page without waiting for the async webhook.
     *
     * Idempotent: a payment already in a terminal-success state is treated
     * as success and short-circuits, mirroring the webhook pipeline's
     * Stage 4 idempotency check.
     *
     * @return Result<\App\Payments\Domain\Enums\TransactionStatus>
     */
    public function verifyCheckoutCallback(
        string $razorpayOrderId,
        string $razorpayPaymentId,
        string $razorpaySignature,
    ): Result {
        return $this->orchestrator->verifyCheckoutCallback(
            $razorpayOrderId,
            $razorpayPaymentId,
            $razorpaySignature,
        );
    }
}