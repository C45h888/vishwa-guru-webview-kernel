<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Adapters;

use App\Payments\Domain\DTOs\GatewayResponseDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\ValueObjects\Identifier;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayAdapter;
use App\Payments\Infrastructure\Adapters\Razorpay\RazorpayClient;
use App\Shared\ValueObjects\Identifier as IdentifierVO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

class RazorpayAdapterTest extends TestCase
{
    private RazorpayAdapter $adapter;
    private RazorpayClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a RazorpayClient with fake credentials
        $this->client = new RazorpayClient('rzp_test_key', 'rzp_test_secret');

        // Inject a mock API using reflection
        $this->injectMockApi($this->client);

        $this->adapter = new RazorpayAdapter($this->client);
    }

    /**
     * Inject a mock Razorpay API object into the client via reflection.
     *
     * @param array<string, mixed> $recordedResponse
     */
    private function injectMockApi(RazorpayClient $client, array $recordedResponse = []): void
    {
        $reflection = new ReflectionClass($client);
        $apiProp = $reflection->getProperty('api');
        $apiProp->setAccessible(true);

        // Create a minimal mock API that returns recorded responses.
        // PHP 8.3 does not allow `new class` inside property initializers
        // (that's a PHP 8.4 feature), so order/payment are constructed
        // inside the constructor below.
        $mockApi = new class($recordedResponse) {
            /** @var array<string, mixed> */
            private array $responses;

            public object $order;
            public object $payment;

            public function __construct(array $responses)
            {
                $this->responses = $responses;

                $this->order = new class {
                    public function create(array $payload = []): object
                    {
                        return (object) [
                            'id' => 'order_mock_' . uniqid(),
                            'amount' => $payload['amount'] ?? 10000,
                            'currency' => $payload['currency'] ?? 'INR',
                            'status' => 'created',
                            'receipt' => $payload['receipt'] ?? null,
                        ];
                    }

                    public function fetch(string $orderId): object
                    {
                        return (object) [
                            'id' => $orderId,
                            'amount' => 10000,
                            'currency' => 'INR',
                            'status' => 'captured',
                        ];
                    }
                };

                $this->payment = new class {
                    public function fetch(string $paymentId): object
                    {
                        return (object) [
                            'id' => $paymentId,
                            'amount' => 10000,
                            'currency' => 'INR',
                            'status' => 'captured',
                        ];
                    }

                    public function refund(array $body = []): object
                    {
                        return (object) [
                            'id' => 're_' . uniqid(),
                            'status' => 'processed',
                            'amount' => $body['amount'] ?? 10000,
                        ];
                    }
                };
            }
        };

        $apiProp->setValue($client, $mockApi);
    }

    private function injectFailingMockApi(RazorpayClient $client, string $exceptionClass, string $message): void
    {
        $reflection = new ReflectionClass($client);
        $apiProp = $reflection->getProperty('api');
        $apiProp->setAccessible(true);

        $mockApi = new class($exceptionClass, $message) {
            public function __construct(private string $exClass, private string $exMessage) {}

            public object $order;

            public function __get(string $name): object
            {
                return new class($this->exClass, $this->exMessage) {
                    public function __construct(private string $exClass, private string $exMessage) {}

                    public function create(array $payload = []): void
                    {
                        throw new $this->exClass($this->exMessage);
                    }

                    public function fetch(string $id): void
                    {
                        throw new $this->exClass($this->exMessage);
                    }
                };
            }
        };

        $apiProp->setValue($client, $mockApi);
    }

    // ─── initialize() ──────────────────────────────────────────────────────

    public function testInitializeSuccess(): void
    {
        $request = new PaymentRequest(
            donorIdentifier: new IdentifierVO('donor_001'),
            amount: 10000,
            currency: Currency::INR,
            purpose: 'General Donation',
            idempotencyKey: 'idem_123',
        );

        $result = $this->adapter->initialize($request);

        $this->assertTrue($result->isOk(), 'Expected success but got: ' . ($result->error() ?? ''));

        /** @var GatewayResponseDTO $response */
        $response = $result->value();

        $this->assertSame('razorpay', $response->providerCode());
        $this->assertNotEmpty($response->gatewayOrderId());
        $this->assertStringStartsWith('order_mock_', $response->gatewayOrderId());
        $this->assertSame(10000, $response->amountMinor());
        $this->assertSame(Currency::INR, $response->currency());
        $this->assertSame('created', $response->rawStatusString());
    }

    public function testInitializeTranslatesRequestToRazorpayPayload(): void
    {
        $request = new PaymentRequest(
            donorIdentifier: new IdentifierVO('donor_xyz'),
            amount: 50000,
            currency: Currency::INR,
            purpose: 'Temple Construction Fund',
            idempotencyKey: 'idem_456',
        );

        $result = $this->adapter->initialize($request);

        $this->assertTrue($result->isOk());
    }

    // ─── verify() ─────────────────────────────────────────────────────────

    public function testVerifyByOrderId(): void
    {
        $result = $this->adapter->verify('order_test_123');

        $this->assertTrue($result->isOk(), 'Expected success but got: ' . ($result->error() ?? ''));
        $this->assertSame(TransactionStatus::CAPTURED, $result->value());
    }

    public function testVerifyByPaymentId(): void
    {
        $result = $this->adapter->verify('order_test_123', 'pay_test_456');

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::CAPTURED, $result->value());
    }

    // ─── capture() ─────────────────────────────────────────────────────────

    public function testCaptureReturnsCapturedStatus(): void
    {
        $txnId = new IdentifierVO('txn_abc');

        $result = $this->adapter->capture($txnId, 10000);

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::CAPTURED, $result->value());
    }

    // ─── refund() ──────────────────────────────────────────────────────────

    public function testRefundSuccess(): void
    {
        $txnId = new IdentifierVO('pay_test_456');

        $result = $this->adapter->refund($txnId, 5000);

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::REFUNDED, $result->value());
    }

    // ─── provider metadata ─────────────────────────────────────────────────

    public function testProviderName(): void
    {
        $this->assertSame('razorpay', $this->adapter->providerName());
    }

    public function testSupportsInr(): void
    {
        $this->assertTrue($this->adapter->supports(Currency::INR));
    }

    public function testDoesNotSupportOtherCurrencies(): void
    {
        $this->assertFalse($this->adapter->supports(Currency::USD));
        $this->assertFalse($this->adapter->supports(Currency::EUR));
    }

    public function testCapabilityMethods(): void
    {
        $this->assertTrue($this->adapter->enabled());
        $this->assertSame(100, $this->adapter->minimumAmount());
        $this->assertSame(99_999_999, $this->adapter->maximumAmount());
        $this->assertSame(10, $this->adapter->priority());
    }

    // ─── Edge cases ────────────────────────────────────────────────────────

    public function testVerifyWithFailedStatus(): void
    {
        // Override the mock API to return failed status
        $reflection = new ReflectionClass($this->client);
        $apiProp = $reflection->getProperty('api');
        $apiProp->setAccessible(true);

        $mockOrder = new class {
            public function fetch(string $orderId): object
            {
                return (object) [
                    'id' => $orderId,
                    'status' => 'failed',
                    'amount' => 10000,
                    'currency' => 'INR',
                ];
            }
        };

        $mockApi = new stdClass();
        $mockApi->order = $mockOrder;
        $apiProp->setValue($this->client, $mockApi);

        $result = $this->adapter->verify('order_failed');

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::FAILED, $result->value());
    }

    public function testVerifyWithAuthorizedStatus(): void
    {
        $reflection = new ReflectionClass($this->client);
        $apiProp = $reflection->getProperty('api');
        $apiProp->setAccessible(true);

        $mockOrder = new class {
            public function fetch(string $orderId): object
            {
                return (object) [
                    'id' => $orderId,
                    'status' => 'authorized',
                    'amount' => 10000,
                    'currency' => 'INR',
                ];
            }
        };

        $mockApi = new stdClass();
        $mockApi->order = $mockOrder;
        $apiProp->setValue($this->client, $mockApi);

        $result = $this->adapter->verify('order_auth');

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::AUTHORIZED, $result->value());
    }

    // ─── C4: real-SDK payload shape assertions (Razorpay /v1/orders) ────

    public function testCapturedCreateOrderPayloadMatchesRazorpaySchema(): void
    {
        // We capture the payload by holding a reference to the order mock
        // outside the closure. The mock exposes a public array property
        // that the create() method appends into.
        $mockOrder = new class {
            /** @var array<int, array<string, mixed>> */
            public array $captured = [];

            public function create(array $payload = []): object
            {
                $this->captured[] = $payload;

                return (object) [
                    'id' => 'order_shape_' . uniqid(),
                    'amount' => $payload['amount'] ?? 0,
                    'currency' => $payload['currency'] ?? 'INR',
                    'status' => 'created',
                    'receipt' => $payload['receipt'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ];
            }
        };

        $reflection = new ReflectionClass($this->client);
        $apiProp = $reflection->getProperty('api');
        $apiProp->setAccessible(true);

        $mockApi = new stdClass();
        $mockApi->order = $mockOrder;
        $apiProp->setValue($this->client, $mockApi);

        $request = new PaymentRequest(
            donorIdentifier: new IdentifierVO('don_shape_1'),
            amount: 50000,
            currency: Currency::INR,
            purpose: 'General Donation',
            metadata: [
                'donation_id' => 'don_shape_1',
                'campaign_id' => 'cmp_shape_1',
            ],
            idempotencyKey: 'idem_shape_1',
        );

        $result = $this->adapter->initialize($request);

        $this->assertTrue($result->isOk(), 'initialize should succeed');

        $this->assertNotEmpty(
            $mockOrder->captured,
            'createOrder should have been invoked with at least one payload',
        );

        $payload = $mockOrder->captured[0];

        // Required Razorpay /v1/orders fields with the exact shape we send
        $this->assertSame(50000, $payload['amount'], 'amount in paise');
        $this->assertSame('INR', $payload['currency'], 'currency code');
        $this->assertSame('idem_shape_1', $payload['receipt'], 'receipt holds the idempotency key');
        $this->assertArrayHasKey('notes', $payload);
        $this->assertIsArray($payload['notes']);
        $this->assertArrayHasKey('donation_id', $payload['notes']);
        $this->assertSame('don_shape_1', $payload['notes']['donation_id']);
        $this->assertArrayHasKey('purpose', $payload['notes']);
        $this->assertSame('General Donation', $payload['notes']['purpose']);
        // Razorpay auto-captures iff payment_capture=1; auto-capture is
        // the canonical mode for Standard Checkout donations.
        $this->assertSame(1, $payload['payment_capture'], 'auto-capture enabled');
    }
}
