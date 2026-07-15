<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\DTOs;

use App\Payments\Domain\DTOs\GatewayRequestDTO;
use App\Payments\Domain\ValueObjects\DonorIdentity;
use App\Payments\Domain\ValueObjects\PaymentIntent;
use App\Payments\Domain\Enums\Currency;
use App\Persistence\ValueObjects\EntityId;
use App\Shared\ValueObjects\Identifier;
use PHPUnit\Framework\TestCase;

class GatewayRequestDTOTest extends TestCase
{
    private function makeIntent(): PaymentIntent
    {
        return new PaymentIntent(
            donationId: new Identifier('01ARZ3NDEKTSV4RRFFQ69G5FAV'),
            donor: DonorIdentity::identified('Test Donor', 'test@example.com'),
            amountMinor: 10000,
            currency: Currency::INR,
            purpose: 'General donation',
            idempotencyKey: 'idem_test_1',
        );
    }

    public function testConstruction(): void
    {
        $intent = $this->makeIntent();
        $dto = new GatewayRequestDTO($intent);

        $this->assertSame($intent, $dto->intent());
        $this->assertSame([], $dto->providerHints());
    }

    public function testWithHint(): void
    {
        $dto = new GatewayRequestDTO($this->makeIntent());
        $dto2 = $dto->withHint('capture_method', 'automatic');

        $this->assertNotSame($dto, $dto2);
        $this->assertSame([], $dto->providerHints());
        $this->assertSame(['capture_method' => 'automatic'], $dto2->providerHints());
    }

    public function testToArray(): void
    {
        $dto = new GatewayRequestDTO($this->makeIntent(), ['key' => 'value']);
        $array = $dto->toArray();

        $this->assertArrayHasKey('intent', $array);
        $this->assertArrayHasKey('provider_hints', $array);
        $this->assertSame(['key' => 'value'], $array['provider_hints']);
    }
}
