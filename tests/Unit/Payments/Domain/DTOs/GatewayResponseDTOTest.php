<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\DTOs;

use App\Payments\Domain\DTOs\GatewayResponseDTO;
use App\Payments\Domain\Enums\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class GatewayResponseDTOTest extends TestCase
{
    public function testConstruction(): void
    {
        $dto = new GatewayResponseDTO(
            providerCode: 'razorpay',
            gatewayOrderId: 'order_ABC',
            gatewayPaymentId: 'pay_XYZ',
            rawStatusString: 'paid',
            amountMinor: 5000,
            currency: Currency::INR,
            rawResponse: ['id' => 'order_ABC'],
            checkoutUrl: 'https://gateway.example/checkout/order_ABC',
            method: 'card',
        );

        $this->assertSame('razorpay', $dto->providerCode());
        $this->assertSame('order_ABC', $dto->gatewayOrderId());
        $this->assertSame('pay_XYZ', $dto->gatewayPaymentId());
        $this->assertSame('paid', $dto->rawStatusString());
        $this->assertSame(5000, $dto->amountMinor());
        $this->assertSame(Currency::INR, $dto->currency());
        $this->assertTrue($dto->hasCheckoutUrl());
        $this->assertTrue($dto->hasPaymentId());
        $this->assertSame('card', $dto->method());
    }

    public function testEmptyOrderId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GatewayResponseDTO(
            providerCode: 'razorpay',
            gatewayOrderId: '',
            gatewayPaymentId: null,
            rawStatusString: 'paid',
            amountMinor: 5000,
            currency: Currency::INR,
        );
    }

    public function testZeroAmount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new GatewayResponseDTO(
            providerCode: 'razorpay',
            gatewayOrderId: 'order_ABC',
            gatewayPaymentId: null,
            rawStatusString: 'paid',
            amountMinor: 0,
            currency: Currency::INR,
        );
    }

    public function testWithoutCheckoutUrl(): void
    {
        $dto = new GatewayResponseDTO(
            providerCode: 'paypal',
            gatewayOrderId: 'order_DEF',
            gatewayPaymentId: null,
            rawStatusString: 'COMPLETED',
            amountMinor: 10000,
            currency: Currency::USD,
        );

        $this->assertFalse($dto->hasCheckoutUrl());
        $this->assertFalse($dto->hasPaymentId());
        $this->assertNull($dto->checkoutUrl());
    }

    public function testToArray(): void
    {
        $dto = new GatewayResponseDTO(
            providerCode: 'razorpay',
            gatewayOrderId: 'order_X',
            gatewayPaymentId: 'pay_Y',
            rawStatusString: 'paid',
            amountMinor: 1000,
            currency: Currency::INR,
        );
        $array = $dto->toArray();
        $this->assertSame('razorpay', $array['provider_code']);
        $this->assertSame('paid', $array['raw_status_string']);
        $this->assertSame('INR', $array['currency']);
    }
}
