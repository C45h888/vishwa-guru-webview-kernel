<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Adapters;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayClient;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayVerificationAdapter;
use App\Shared\Contracts\ConfigurationContract;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RazorpayVerificationAdapterTest extends TestCase
{
    private RazorpayVerificationAdapter $adapter;
    private RazorpayClient $client;
    private ConfigurationContract $config;
    private string $webhookSecret = 'webhook_test_secret_12345';

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = new RazorpayClient('rzp_test_key', 'rzp_test_secret');
        $this->config = $this->createMock(ConfigurationContract::class);

        $this->config->method('get')
            ->willReturnCallback(function (string $key, $default = null) {
                return match ($key) {
                    'payments.providers.razorpay.webhook_secret' => $this->webhookSecret,
                    default => $default,
                };
            });

        $this->adapter = new RazorpayVerificationAdapter($this->client, $this->config);
    }

    // ─── verifyWebhook() ──────────────────────────────────────────────────

    public function testVerifyWebhookSucceedsWithValidSignature(): void
    {
        $payload = json_encode([
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_test_123',
                        'amount' => 10000,
                        'status' => 'captured',
                        'currency' => 'INR',
                        'method' => 'upi',
                    ],
                ],
                'order' => [
                    'entity' => [
                        'id' => 'order_test_123',
                    ],
                ],
            ],
        ], true);

        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $result = $this->adapter->verifyWebhook(
            ['x-razorpay-signature' => $signature],
            $payload,
        );

        $this->assertTrue($result->isOk(), 'Expected success but got: ' . ($result->error() ?? ''));

        $data = $result->value();
        $this->assertSame('order_test_123', $data['gateway_order_id']);
        $this->assertSame('pay_test_123', $data['gateway_payment_id']);
        $this->assertSame(TransactionStatus::CAPTURED, $data['status']);
        $this->assertSame(10000, $data['amount']);
    }

    public function testVerifyWebhookFailsWithMissingSignature(): void
    {
        $result = $this->adapter->verifyWebhook([], '{"event":"payment.captured"}');

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('signature', strtolower($result->error() ?? ''));
    }

    public function testVerifyWebhookFailsWithInvalidSignature(): void
    {
        $payload = '{"event":"payment.captured"}';

        $result = $this->adapter->verifyWebhook(
            ['x-razorpay-signature' => 'invalid_signature_here'],
            $payload,
        );

        $this->assertTrue($result->isFailure());
        $this->assertStringContainsString('signature', strtolower($result->error() ?? ''));
    }

    public function testVerifyWebhookFailsWithMalformedPayload(): void
    {
        $signature = hash_hmac('sha256', 'not-valid-json', $this->webhookSecret);

        $result = $this->adapter->verifyWebhook(
            ['x-razorpay-signature' => $signature],
            'not-valid-json',
        );

        // JSON decode fails, but we still return failure
        $this->assertTrue($result->isFailure());
    }

    public function testVerifyWebhookExtractsAllFields(): void
    {
        $payload = json_encode([
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_abc',
                        'amount' => 50000,
                        'status' => 'authorized',
                        'currency' => 'INR',
                        'method' => 'card',
                    ],
                ],
                'order' => [
                    'entity' => [
                        'id' => 'order_abc',
                    ],
                ],
            ],
        ]);

        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $result = $this->adapter->verifyWebhook(
            ['x-razorpay-signature' => $signature],
            $payload,
        );

        $this->assertTrue($result->isOk());

        $data = $result->value();
        $this->assertSame('order_abc', $data['gateway_order_id']);
        $this->assertSame('pay_abc', $data['gateway_payment_id']);
        $this->assertSame(TransactionStatus::AUTHORIZED, $data['status']);
        $this->assertSame(50000, $data['amount']);
        $this->assertSame('INR', $data['currency']);
        $this->assertSame('card', $data['method']);
    }

    public function testVerifyWebhookHandlesMissingEntityFields(): void
    {
        $payload = json_encode([
            'payload' => [
                'payment' => ['entity' => []],
                'order' => ['entity' => []],
            ],
        ]);

        $signature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $result = $this->adapter->verifyWebhook(
            ['x-razorpay-signature' => $signature],
            $payload,
        );

        $this->assertTrue($result->isOk());

        $data = $result->value();
        $this->assertSame('', $data['gateway_order_id']);
        $this->assertSame('', $data['gateway_payment_id']);
        $this->assertSame(0, $data['amount']);
    }

    // ─── verifySignature() ─────────────────────────────────────────────────

    public function testVerifySignatureDelegatesToClient(): void
    {
        $payload = '{"test":"data"}';
        $validSignature = hash_hmac('sha256', $payload, $this->webhookSecret);

        $result = $this->adapter->verifySignature($payload, $validSignature);

        $this->assertTrue($result);
    }

    public function testVerifySignatureReturnsFalseForTamperedPayload(): void
    {
        $originalPayload = '{"amount":100}';
        $tamperedPayload = '{"amount":999}';
        $signature = hash_hmac('sha256', $originalPayload, $this->webhookSecret);

        $result = $this->adapter->verifySignature($tamperedPayload, $signature);

        $this->assertFalse($result);
    }

    // ─── generateSignature() ──────────────────────────────────────────────

    public function testGenerateSignature(): void
    {
        $payload = '{"event":"test"}';

        $signature = $this->adapter->generateSignature($payload);

        $expected = hash_hmac('sha256', $payload, $this->webhookSecret);
        $this->assertSame($expected, $signature);
    }

    public function testGeneratedSignatureVerifies(): void
    {
        $payload = '{"donation":"test"}';

        $signature = $this->adapter->generateSignature($payload);

        $this->assertTrue($this->adapter->verifySignature($payload, $signature));
    }
}
