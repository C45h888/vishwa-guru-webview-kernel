<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Adapters;

use App\Payments\Domain\DTOs\GatewayResponseDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\ValueObjects\Identifier;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Payments\Infrastructure\Adapters\InMemory\InMemoryGatewayAdapter;
use App\Payments\Infrastructure\Adapters\InMemory\InMemoryProviderAdapter;
use App\Payments\Infrastructure\Adapters\InMemory\InMemoryVerificationAdapter;
use App\Shared\Support\Result;
use PHPUnit\Framework\TestCase;

class InMemoryAdapterTest extends TestCase
{
    // ─── InMemoryGatewayAdapter ─────────────────────────────────────────────

    public function testInitializeReturnsScriptedSuccess(): void
    {
        $adapter = new InMemoryGatewayAdapter();
        $request = $this->makeRequest();

        $response = new GatewayResponseDTO(
            providerCode: 'razorpay',
            gatewayOrderId: 'order_test_123',
            gatewayPaymentId: null,
            rawStatusString: 'created',
            amountMinor: 10000,
            currency: Currency::INR,
            rawResponse: [],
        );

        $adapter->setNextInitializeResult(Result::success($response));

        $result = $adapter->initialize($request);

        $this->assertTrue($result->isOk());
        $this->assertSame('order_test_123', $result->value()->gatewayOrderId());

        $calls = $adapter->getInitializeCalls();
        $this->assertCount(1, $calls);
        $this->assertSame($request, $calls[0]['request']);
    }

    public function testInitializeReturnsScriptedFailure(): void
    {
        $adapter = new InMemoryGatewayAdapter();
        $request = $this->makeRequest();

        $adapter->setNextInitializeResult(
            Result::failure('Simulated initialization failure'),
        );

        $result = $adapter->initialize($request);

        $this->assertTrue($result->isFailure());
        $this->assertSame('Simulated initialization failure', $result->error());
    }

    public function testVerifyReturnsScriptedStatus(): void
    {
        $adapter = new InMemoryGatewayAdapter();
        $adapter->setNextVerifyResult(Result::success(TransactionStatus::CAPTURED));

        $result = $adapter->verify('order_test_123');

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::CAPTURED, $result->value());

        $calls = $adapter->getVerifyCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('order_test_123', $calls[0]['gatewayOrderId']);
    }

    public function testCaptureReturnsScriptedResult(): void
    {
        $adapter = new InMemoryGatewayAdapter();
        $id = new Identifier('txn_abc');

        $adapter->setNextCaptureResult(Result::success(TransactionStatus::CAPTURED));

        $result = $adapter->capture($id, 10000);

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::CAPTURED, $result->value());

        $calls = $adapter->getCaptureCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('txn_abc', $calls[0]['transactionId']->value());
    }

    public function testRefundReturnsScriptedResult(): void
    {
        $adapter = new InMemoryGatewayAdapter();
        $id = new Identifier('txn_abc');

        $adapter->setNextRefundResult(Result::success(TransactionStatus::REFUNDED));

        $result = $adapter->refund($id, 5000);

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::REFUNDED, $result->value());
    }

    public function testCallsAreRecordedInOrder(): void
    {
        $adapter = new InMemoryGatewayAdapter();
        $request = $this->makeRequest();

        $adapter->setNextInitializeResult(Result::success(new GatewayResponseDTO(
            providerCode: 'razorpay',
            gatewayOrderId: 'order_1',
            gatewayPaymentId: null,
            rawStatusString: 'created',
            amountMinor: 10000,
            currency: Currency::INR,
            rawResponse: [],
        )));

        $adapter->initialize($request);
        $adapter->verify('order_1');
        $adapter->capture(new Identifier('txn_1'), 10000);

        $allCalls = $adapter->getInitializeCalls()
            + $adapter->getVerifyCalls()
            + $adapter->getCaptureCalls();

        $this->assertCount(3, $allCalls);
    }

    public function testGatewayAdapterMetadataMethods(): void
    {
        $adapter = new InMemoryGatewayAdapter();
        $adapter->setEnabled(true)->setMinimumAmount(200)->setMaximumAmount(50_000_000)->setPriority(5);

        $this->assertSame('inmemory', $adapter->providerName());
        $this->assertTrue($adapter->supports(Currency::INR));
        $this->assertTrue($adapter->enabled());
        $this->assertSame(200, $adapter->minimumAmount());
        $this->assertSame(50_000_000, $adapter->maximumAmount());
        $this->assertSame(5, $adapter->priority());
    }

    public function testResetCalls(): void
    {
        $adapter = new InMemoryGatewayAdapter();
        $adapter->initialize($this->makeRequest());

        $this->assertCount(1, $adapter->getInitializeCalls());

        $adapter->resetCalls();

        $this->assertCount(0, $adapter->getInitializeCalls());
    }

    // ─── InMemoryVerificationAdapter ──────────────────────────────────────

    public function testVerifyWebhookReturnsScriptedResult(): void
    {
        $adapter = new InMemoryVerificationAdapter('secret123');

        $scriptedPayload = [
            'gateway_order_id' => 'order_xyz',
            'gateway_payment_id' => 'pay_xyz',
            'status' => TransactionStatus::CAPTURED,
            'amount' => 10500,
        ];

        $adapter->setNextWebhookResult(Result::success($scriptedPayload));

        $result = $adapter->verifyWebhook(['x-sig' => 'abc'], '{"test":"payload"}');

        $this->assertTrue($result->isOk());
        $this->assertSame('order_xyz', $result->value()['gateway_order_id']);

        $calls = $adapter->getWebhookCalls();
        $this->assertCount(1, $calls);
    }

    public function testVerifySignatureWithCorrectHmac(): void
    {
        $secret = 'my-secret-key';
        $adapter = new InMemoryVerificationAdapter($secret);

        $payload = '{"event":"payment.captured"}';
        $validSignature = hash_hmac('sha256', $payload, $secret);

        $this->assertTrue($adapter->verifySignature($payload, $validSignature));
    }

    public function testVerifySignatureWithIncorrectHmac(): void
    {
        $adapter = new InMemoryVerificationAdapter('my-secret-key');

        $payload = '{"event":"payment.captured"}';
        $wrongSignature = 'invalid_signature_here';

        $this->assertFalse($adapter->verifySignature($payload, $wrongSignature));
    }

    public function testGenerateSignature(): void
    {
        $adapter = new InMemoryVerificationAdapter('my-secret-key');

        $payload = '{"test":"data"}';
        $signature = $adapter->generateSignature($payload);

        $this->assertSame(hash_hmac('sha256', $payload, 'my-secret-key'), $signature);
    }

    // ─── InMemoryProviderAdapter ───────────────────────────────────────────

    public function testProviderAdapterDefaults(): void
    {
        $adapter = InMemoryProviderAdapter::createDefault();

        $this->assertSame('inmemory', $adapter->name());
        $this->assertSame('In-Memory Test Provider', $adapter->displayName());
        $this->assertTrue($adapter->isEnabled());
        $this->assertSame(100, $adapter->minimumAmount());
        $this->assertSame(99_999_999, $adapter->maximumAmount());
        $this->assertSame(100, $adapter->priority());
    }

    public function testProviderAdapterCustomConfiguration(): void
    {
        $adapter = new InMemoryProviderAdapter(
            enabled: false,
            supportedCurrencies: [Currency::USD],
            minimumAmount: 500,
            maximumAmount: 10_000_000,
            priority: 50,
        );

        $this->assertFalse($adapter->isEnabled());
        $this->assertSame([Currency::USD], $adapter->supportedCurrencies());
        $this->assertSame(500, $adapter->minimumAmount());
        $this->assertSame(10_000_000, $adapter->maximumAmount());
        $this->assertSame(50, $adapter->priority());
    }

    // ─── Helper ─────────────────────────────────────────────────────────────

    private function makeRequest(): PaymentRequest
    {
        return new PaymentRequest(
            donorIdentifier: new Identifier('donor_001'),
            amount: 10000,
            currency: Currency::INR,
            purpose: 'Test Donation',
            idempotencyKey: 'idem_001',
        );
    }
}
