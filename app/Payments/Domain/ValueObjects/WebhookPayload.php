<?php

declare(strict_types=1);

namespace App\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\PaymentProvider;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Raw, unverified webhook callback delivered by a payment gateway.
 *
 * The PaymentService hands a WebhookPayload to the
 * PaymentVerificationPipeline which is responsible for signature
 * verification, parsing, and conversion into a PaymentVerification.
 *
 * WebhookPayload is intentionally provider-agnostic: raw headers and
 * raw body are preserved verbatim so each gateway's PaymentVerificationContract
 * can apply its own decoding logic.
 */
final class WebhookPayload
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $metadata  Optional routing metadata (received IP, request ID)
     */
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly array $headers,
        private readonly string $rawBody,
        private readonly DateTimeImmutable $receivedAt,
        private readonly string $providerEventId = '',
        private readonly array $metadata = [],
    ) {
        if (empty($headers)) {
            throw new InvalidArgumentException('WebhookPayload headers cannot be empty');
        }
        if ($rawBody === '') {
            throw new InvalidArgumentException('WebhookPayload raw body cannot be empty');
        }
    }

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function receivedAt(): DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function providerEventId(): string
    {
        return $this->providerEventId;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    /**
     * Read a single header by name (case-insensitive).
     */
    public function header(string $name): ?string
    {
        $needle = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower($key) === $needle) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'headers' => $this->headers,
            'raw_body' => $this->rawBody,
            'received_at' => $this->receivedAt->format(DATE_ATOM),
            'provider_event_id' => $this->providerEventId,
            'metadata' => $this->metadata,
        ];
    }
}