<?php

declare(strict_types=1);

namespace App\Payments\Contracts;

use App\Payments\Enums\TransactionStatus;
use App\Shared\Support\Result;

/**
 * Verifies webhook callbacks from payment gateways.
 * Implements cryptographic signature verification to ensure
 * callbacks originated from the gateway, not a malicious actor.
 */
interface PaymentVerificationContract
{
    /**
     * Verify a webhook callback.
     *
     * @param  array<string, string>  $headers  HTTP headers from the webhook
     * @param  string  $payload  Raw request body
     * @return Result<array{gateway_order_id: string, gateway_payment_id: string, status: TransactionStatus, amount: int}>
     */
    public function verifyWebhook(array $headers, string $payload): Result;

    /**
     * Verify a signature string against expected payload.
     */
    public function verifySignature(string $payload, string $signature): bool;

    /**
     * Generate a signature for outgoing requests or test fixtures.
     */
    public function generateSignature(string $payload): string;
}
