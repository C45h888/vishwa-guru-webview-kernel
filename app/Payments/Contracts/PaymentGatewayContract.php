<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Domain\DTOs\GatewayResponseDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Shared\Support\Result;
use App\Shared\ValueObjects\Identifier;

/**
 * Authoritative gateway for payment operations.
 *
 * Implements the canonical payment workflow:
 *   Initialization → Gateway Processing → Webhook Callback
 *   → Signature Verification → Persistence → Receipt → Notification → Audit
 *
 * No donation is considered successful until the gateway has verified
 * the transaction.
 */
interface PaymentGatewayContract
{
    /**
     * Initialize a new payment transaction.
     * Returns an order identifier from the gateway.
     *
     * @return Result<GatewayResponseDTO>
     */
    public function initialize(PaymentRequest $request): Result;

    /**
     * Verify a payment via the gateway's verification API.
     * Used as the authoritative source of payment truth.
     *
     * @return Result<TransactionStatus>
     */
    public function verify(string $gatewayOrderId, ?string $gatewayPaymentId = null): Result;

    /**
     * Capture a previously authorized payment.
     *
     * @return Result<TransactionStatus>
     */
    public function capture(Identifier $transactionId, int $amount): Result;

    /**
     * Refund a settled payment.
     *
     * @return Result<TransactionStatus>
     */
    public function refund(Identifier $transactionId, int $amount): Result;

    /**
     * Determine the gateway provider name (e.g. "razorpay", "paypal").
     */
    public function providerName(): string;

    /**
     * Whether this gateway supports the given currency.
     */
    public function supports(Currency $currency): bool;

    /**
     * Whether this gateway is currently enabled. Mirrors the
     * `enabled` column on the `payment_providers` row and the
     * PaymentProvider enum's isEnabled() but is adapter-controlled
     * because some adapters may disable themselves at runtime
     * (e.g. sandbox-only Razorpay keys).
     */
    public function enabled(): bool;

    /**
     * The minimum amount (in MINOR units of the gateway's primary
     * currency) this gateway accepts. Used by the
     * PaymentProviderSelector to filter the candidate pool.
     */
    public function minimumAmount(): int;

    /**
     * The maximum amount (in MINOR units of the gateway's primary
     * currency) this gateway accepts per single transaction. Used
     * by the PaymentProviderSelector to filter the candidate pool.
     */
    public function maximumAmount(): int;

    /**
     * Selection priority for the PaymentProviderSelector. Lower
     * values are selected first. Mirrors the PaymentProvider enum's
     * priority() but is adapter-controlled so per-environment
     * overrides are possible without changing domain code.
     */
    public function priority(): int;
}
