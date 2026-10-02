<?php

declare(strict_types=1);

namespace App\Mail\Contracts;

use App\Shared\Support\Result;

/**
 * MailTransportContract — the provider seam for outbound mail.
 *
 * The receipt-delivery workflow (Payments money-lifecycle: receipt →
 * delivery) talks to THIS contract, never to a provider. The Hostinger
 * Mail API is the first implementation (App\Payments\Mail\MailSubstrate);
 * any future provider is one more adapter on this seam.
 *
 * Mirrors the Shape-A adapter doctrine used by PaymentGatewayContract.
 */
interface MailTransportContract
{
    /**
     * Send one message.
     *
     * @param  array<int, string>          $to          Recipient addresses
     * @param  array<int, array<string, mixed>>  $attachments  Optional
     *     attachments as {filename, content, content_type, encoding}
     * @return Result<array{accepted: bool, dry_run: bool, from: string|null}>
     */
    public function send(
        array $to,
        string $subject,
        string $text,
        string $html,
        ?string $displayName = null,
        array $attachments = [],
    ): Result;

    /**
     * Whether the transport is configured and allowed to send.
     */
    public function isEnabled(): bool;
}
