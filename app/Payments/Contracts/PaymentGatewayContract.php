<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Enums\Currency;
use App\Payments\Enums\TransactionStatus;
use App\Payments\ValueObjects\PaymentRequest;
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
     * @return Result<array{order_id: string, amount: int, currency: Currency}>
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
}
