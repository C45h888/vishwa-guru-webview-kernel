<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Adapters;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalClient;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalVerificationAdapter;
use App\Shared\Contracts\ConfigurationContract;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class PayPalVerificationAdapterTest extends TestCase
{
    private PayPalVerificationAdapter $adapter;
    private PayPalClient $client;
    private ConfigurationContract $config;
    private string $webhookId = '8JH2T3F4L5J6K7L8M';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new PayPalClient('client_id', 'client_secret', true);
        $this->config = $this->createMock(ConfigurationContract::class);

        $this->config->method('get')
            ->willReturnCallback(function (string $key, $default = null) {
                return match ($key) {
                    'payments.providers.paypal.webhook_id' => $this->webhookId,
                    default => $default,
                };
            });

        $this->adapter = new PayPalVerificationAdapter($this->client, $this->config);
    }

    // ─── verifyWebhook() ──────────────────────────────────────────────────

    public function testVerifyWebhookSucceedsWithValidHeaders(): void
    {
        $payload = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => 'capture_abc123',
                'status' => 'COMPLETED',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => '25.00',
                ],
                'payment_method' => 'paypal',
            ],
            'resource_id' => 'capture_abc123',
        ]);

        $signature = $this->computeSignature($payload);

        $result = $this->adapter->verifyWebhook([
            'paypal-transmission-sig' => $signature,
            'paypal-transmission-id' => 'txn_12345',
            'paypal-transmission-time' => '2024-01-01T12:00:00Z',
        ], $payload);

        $this->assertTrue($result->isOk(), 'Expected success but got: ' . ($result->error() ?? ''));

        $data = $result->value();
        $this->assertSame('capture_abc123', $data['gateway_order_id']);
        $this->assertSame('capture_abc123', $data['gateway_payment_id']);
        $this->assertSame(TransactionStatus::CAPTURED, $data['status']);
        $this->assertSame(2500, $data['amount']); // $25.00 = 2500 cents
        $this->assertSame('USD', $data['currency']);
    }

    public function testVerifyWebhookFailsWithMissingSignature(): void
    {
        $result = $this->adapter->verifyWebhook(
            ['paypal-transmission-id' => 'txn_123'],
            '{"event_type":"test"}',
        );

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('signature', strtolower($result->error() ?? ''));
    }

    public function testVerifyWebhookFailsWithMissingTransmissionId(): void
    {
        $result = $this->adapter->verifyWebhook(
            ['paypal-transmission-sig' => 'some_sig'],
            '{"event_type":"test"}',
        );

        $this->assertTrue($result->isFailure());
    }

    public function testVerifyWebhookExtractsOrderIdFromResource(): void
    {
        $payload = json_encode([
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [
                'id' => 'ORDER-REF-123',
                'status' => 'APPROVED',
                'amount' => [
                    'currency_code' => 'EUR',
                    'value' => '50.00',
                ],
            ],
            'resource_id' => 'ORDER-REF-123',
        ]);

        $signature = $this->computeSignature($payload);

        $result = $this->adapter->verifyWebhook([
            'paypal-transmission-sig' => $signature,
            'paypal-transmission-id' => 'txn_67890',
            'paypal-transmission-time' => '2024-01-01T12:00:00Z',
        ], $payload);

        $this->assertTrue($result->isOk());

        $data = $result->value();
        $this->assertSame('ORDER-REF-123', $data['gateway_order_id']);
        $this->assertSame(TransactionStatus::AUTHORIZED, $data['status']);
    }

    public function testVerifyWebhookHandlesPartiallyRefunded(): void
    {
        $payload = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'id' => 'refund_xyz',
                'status' => 'PARTIALLY_REFUNDED',
                'amount' => [
                    'currency_code' => 'GBP',
                    'value' => '5.00',
                ],
            ],
        ]);

        $signature = $this->computeSignature($payload);

        $result = $this->adapter->verifyWebhook([
            'paypal-transmission-sig' => $signature,
            'paypal-transmission-id' => 'txn_gbp',
            'paypal-transmission-time' => '2024-01-01T12:00:00Z',
        ], $payload);

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::PARTIALLY_REFUNDED, $result->value()['status']);
    }

    public function testVerifyWebhookHandlesFailedCapture(): void
    {
        $payload = json_encode([
            'event_type' => 'PAYMENT.CAPTURE.DENIED',
            'resource' => [
                'id' => 'capture_denied',
                'status' => 'DENIED',
                'amount' => [
                    'currency_code' => 'CAD',
                    'value' => '15.00',
                ],
            ],
        ]);

        $signature = $this->computeSignature($payload);

        $result = $this->adapter->verifyWebhook([
            'paypal-transmission-sig' => $signature,
            'paypal-transmission-id' => 'txn_denied',
            'paypal-transmission-time' => '2024-01-01T12:00:00Z',
        ], $payload);

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::FAILED, $result->value()['status']);
    }

    // ─── verifySignature() ───────────────────────────────────────────────

    public function testVerifySignatureWithCorrectHmac(): void
    {
        $payload = '{"event":"test"}';
        // Compute expected: hash_hmac('sha256', $webhookId, $signature) = payload
        // So: expected = hash_hmac('sha256', '8JH2T3F4L5J6K7L8M', $payload)
        $signature = hash_hmac('sha256', $this->webhookId, $payload);

        $result = $this->adapter->verifySignature($payload, $signature);

        $this->assertTrue($result);
    }

    public function testVerifySignatureWithIncorrectHmac(): void
    {
        $result = $this->adapter->verifySignature('{"test":"data"}', 'wrong_signature');

        $this->assertFalse($result);
    }

    // ─── generateSignature() ──────────────────────────────────────────────

    public function testGenerateSignature(): void
    {
        $payload = '{"event_type":"test"}';

        $signature = $this->adapter->generateSignature($payload);

        $expected = hash_hmac('sha256', $payload, $this->webhookId);
        $this->assertSame($expected, $signature);
    }

    public function testGeneratedSignatureVerifies(): void
    {
        $payload = '{"donation":"verified"}';

        $signature = $this->adapter->generateSignature($payload);

        $this->assertTrue($this->adapter->verifySignature($payload, $signature));
    }

    // ─── Helper ──────────────────────────────────────────────────────────

    private function computeSignature(string $payload): string
    {
        $transmissionId = 'txn_12345';
        $transmissionTime = '2024-01-01T12:00:00Z';

        // Signature payload per PayPal webhook spec: transmissionId|transmissionTime|webhookId|crc32(payload)
        $signaturePayload = $transmissionId . '|' . $transmissionTime . '|' . $this->webhookId . '|' . crc32($payload);

        return hash_hmac('sha256', $signaturePayload, $this->webhookId);
    }
}
