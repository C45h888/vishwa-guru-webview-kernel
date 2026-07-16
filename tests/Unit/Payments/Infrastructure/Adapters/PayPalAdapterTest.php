<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Adapters;

use App\Payments\Domain\DTOs\GatewayResponseDTO;
use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\ValueObjects\Identifier;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalAdapter;
use App\Payments\Infrastructure\Adapters\PayPal\PayPalClient;
use App\Shared\ValueObjects\Identifier as IdentifierVO;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

class PayPalAdapterTest extends TestCase
{
    private PayPalAdapter $adapter;
    private PayPalClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Create client with sandbox=true (test mode)
        $this->client = new PayPalClient('client_id', 'client_secret', true);

        // Inject mock HTTP client via reflection
        $this->injectMockHttpClient($this->client);

        $this->adapter = new PayPalAdapter($this->client);
    }

    private function injectMockHttpClient(PayPalClient $client): void
    {
        $reflection = new ReflectionClass($client);
        $prop = $reflection->getProperty('httpClient');
        $prop->setAccessible(true);

        // Mock HTTP client that returns predictable responses
        $mockClient = new class {
            public function execute(object $request): stdClass
            {
                $result = new stdClass();
                $result->statusCode = 201;
                $result->headers = [];

                if ($request instanceof PayPalCheckoutSdk\Orders\OrdersCreateRequest) {
                    $result->result = $this->makeOrderResponse('ORDER-MOCK-123', 'CREATED', '10.00', 'USD');
                } elseif ($request instanceof PayPalCheckoutSdk\Orders\OrdersGetRequest) {
                    $orderId = $request->orderId ?? 'ORDER-MOCK-123';
                    $result->result = $this->makeOrderResponse($orderId, 'COMPLETED', '10.00', 'USD');
                    $result->result->status = 'COMPLETED';
                } elseif ($request instanceof PayPalCheckoutSdk\Orders\OrdersCaptureRequest) {
                    $result->result = $this->makeOrderResponse('ORDER-MOCK-123', 'COMPLETED', '10.00', 'USD');
                    $result->result->status = 'COMPLETED';
                } elseif ($request instanceof PayPalCheckoutSdk\Payments\CapturesRefundRequest) {
                    $result->result = new stdClass();
                    $result->result->id = 'refund_123';
                    $result->result->status = 'COMPLETED';
                    $result->statusCode = 201;
                }

                return $result;
            }

            private function makeOrderResponse(string $id, string $status, string $value, string $currency): stdClass
            {
                $order = new stdClass();
                $order->id = $id;
                $order->status = $status;
                $order->create_time = '2024-01-01T00:00:00Z';
                $order->update_time = '2024-01-01T00:01:00Z';
                $order->links = [
                    (object) ['rel' => 'approve', 'href' => 'https://sandbox.paypal.com/approve'],
                    (object) ['rel' => 'self', 'href' => 'https://api.paypal.com/v2/checkout/orders/' . $id],
                ];

                $unit = new stdClass();
                $unit->reference_id = 'ref_001';
                $unit->description = 'Test Donation';

                $amount = new stdClass();
                $amount->currency_code = $currency;
                $amount->value = $value;
                $unit->amount = $amount;

                $order->purchase_units = [$unit];

                $payer = new stdClass();
                $payer->email_address = 'donor@example.com';
                $order->payer = $payer;

                return $order;
            }
        };

        $prop->setValue($client, $mockClient);
    }

    // ─── initialize() ─────────────────────────────────────────────────────

    public function testInitializeSuccess(): void
    {
        $request = new PaymentRequest(
            donorIdentifier: new IdentifierVO('donor_pp_001'),
            amount: 1000, // $10.00
            currency: Currency::USD,
            purpose: 'General Donation',
            idempotencyKey: 'idem_pp_123',
        );

        $result = $this->adapter->initialize($request);

        $this->assertTrue($result->isOk(), 'Expected success but got: ' . ($result->error() ?? ''));

        /** @var GatewayResponseDTO $response */
        $response = $result->value();

        $this->assertSame('paypal', $response->providerCode());
        $this->assertSame('ORDER-MOCK-123', $response->gatewayOrderId());
        $this->assertSame('CREATED', $response->rawStatusString());
        $this->assertNotNull($response->checkoutUrl());
        $this->assertSame('paypal', $response->method());
    }

    public function testInitializeWithInrCurrency(): void
    {
        $request = new PaymentRequest(
            donorIdentifier: new IdentifierVO('donor_inr'),
            amount: 10000, // ₹100.00
            currency: Currency::INR,
            purpose: 'Temple Fund',
            idempotencyKey: 'idem_inr',
        );

        $result = $this->adapter->initialize($request);

        // PayPal doesn't support INR directly — should still succeed but result is from mock
        $this->assertTrue($result->isOk());
    }

    // ─── verify() ─────────────────────────────────────────────────────────

    public function testVerifyReturnsCompletedStatus(): void
    {
        $result = $this->adapter->verify('ORDER-MOCK-123');

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::CAPTURED, $result->value());
    }

    // ─── capture() ────────────────────────────────────────────────────────

    public function testCaptureReturnsCapturedStatus(): void
    {
        $txnId = new IdentifierVO('ORDER-MOCK-123');

        $result = $this->adapter->capture($txnId, 1000);

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::CAPTURED, $result->value());
    }

    // ─── refund() ─────────────────────────────────────────────────────────

    public function testRefundReturnsRefundedStatus(): void
    {
        $txnId = new IdentifierVO('capture_abc');

        $result = $this->adapter->refund($txnId, 500);

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::REFUNDED, $result->value());
    }

    // ─── provider metadata ────────────────────────────────────────────────

    public function testProviderName(): void
    {
        $this->assertSame('paypal', $this->adapter->providerName());
    }

    public function testSupportsMultipleCurrencies(): void
    {
        $this->assertTrue($this->adapter->supports(Currency::USD));
        $this->assertTrue($this->adapter->supports(Currency::EUR));
        $this->assertTrue($this->adapter->supports(Currency::GBP));
        $this->assertTrue($this->adapter->supports(Currency::CAD));
    }

    public function testDoesNotSupportInr(): void
    {
        $this->assertFalse($this->adapter->supports(Currency::INR));
    }

    public function testCapabilityMethods(): void
    {
        $this->assertTrue($this->adapter->enabled());
        $this->assertSame(500, $this->adapter->minimumAmount()); // $5.00 in cents
        $this->assertSame(20, $this->adapter->priority());
    }

    // ─── Edge cases ───────────────────────────────────────────────────────

    public function testVerifyMapsVoidedStatusToCancelled(): void
    {
        $reflection = new ReflectionClass($this->client);
        $prop = $reflection->getProperty('httpClient');
        $prop->setAccessible(true);

        $mockClient = new class {
            public function execute(object $request): stdClass
            {
                $result = new stdClass();
                $result->statusCode = 200;
                $result->headers = [];

                $order = new stdClass();
                $order->id = 'ORDER-VOIDED';
                $order->status = 'VOIDED';
                $order->purchase_units = [(object) [
                    'amount' => (object) ['currency_code' => 'USD', 'value' => '10.00'],
                ]];

                $result->result = $order;

                return $result;
            }
        };

        $prop->setValue($this->client, $mockClient);

        $result = $this->adapter->verify('ORDER-VOIDED');

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::CANCELLED, $result->value());
    }

    public function testVerifyMapsApprovedStatusToAuthorized(): void
    {
        $reflection = new ReflectionClass($this->client);
        $prop = $reflection->getProperty('httpClient');
        $prop->setAccessible(true);

        $mockClient = new class {
            public function execute(object $request): stdClass
            {
                $result = new stdClass();
                $result->statusCode = 200;
                $result->result = new stdClass();
                $result->result->id = 'ORDER-APPROVED';
                $result->result->status = 'APPROVED';
                $result->result->purchase_units = [(object) [
                    'amount' => (object) ['currency_code' => 'USD', 'value' => '10.00'],
                ]];

                return $result;
            }
        };

        $prop->setValue($this->client, $mockClient);

        $result = $this->adapter->verify('ORDER-APPROVED');

        $this->assertTrue($result->isOk());
        $this->assertSame(TransactionStatus::AUTHORIZED, $result->value());
    }
}
