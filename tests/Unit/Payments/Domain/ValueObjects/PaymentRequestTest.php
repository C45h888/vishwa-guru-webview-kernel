<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\ValueObjects;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Domain\ValueObjects\PaymentRequest;
use App\Shared\ValueObjects\Identifier;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PaymentRequestTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $donor = Identifier::generate();
        $request = new PaymentRequest(
            donorIdentifier: $donor,
            amount: 50000,
            currency: Currency::INR,
            purpose: 'General Donation'
        );

        $this->assertSame($donor, $request->donorIdentifier());
        $this->assertSame(50000, $request->amount());
        $this->assertSame(Currency::INR, $request->currency());
        $this->assertSame('General Donation', $request->purpose());
        $this->assertNull($request->successUrl());
        $this->assertNull($request->failureUrl());
        $this->assertNull($request->idempotencyKey());
    }

    public function testFullConstruction(): void
    {
        $donor = Identifier::generate();
        $request = new PaymentRequest(
            donorIdentifier: $donor,
            amount: 100000,
            currency: Currency::INR,
            purpose: 'Annadhanam Sponsorship',
            metadata: ['campaign_id' => 'CAMP-001', 'source' => 'web'],
            successUrl: 'https://temple.org/thank-you',
            failureUrl: 'https://temple.org/payment-failed',
            idempotencyKey: 'idem-abc-123'
        );

        $this->assertSame(['campaign_id' => 'CAMP-001', 'source' => 'web'], $request->metadata());
        $this->assertSame('https://temple.org/thank-you', $request->successUrl());
        $this->assertSame('idem-abc-123', $request->idempotencyKey());
    }

    public function testNegativeAmountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PaymentRequest(
            donorIdentifier: Identifier::generate(),
            amount: -1,
            currency: Currency::INR,
            purpose: 'Test'
        );
    }

    public function testZeroAmountThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PaymentRequest(
            donorIdentifier: Identifier::generate(),
            amount: 0,
            currency: Currency::INR,
            purpose: 'Test'
        );
    }

    public function testEmptyPurposeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new PaymentRequest(
            donorIdentifier: Identifier::generate(),
            amount: 100,
            currency: Currency::INR,
            purpose: ''
        );
    }

    public function testToArray(): void
    {
        $donor = Identifier::generate();
        $request = new PaymentRequest(
            donorIdentifier: $donor,
            amount: 25000,
            currency: Currency::INR,
            purpose: 'Donation',
            idempotencyKey: 'idem-001'
        );

        $array = $request->toArray();
        $this->assertSame($donor->value(), $array['donor_id']);
        $this->assertSame(25000, $array['amount']);
        $this->assertSame('INR', $array['currency']);
        $this->assertSame('Donation', $array['purpose']);
        $this->assertSame('idem-001', $array['idempotency_key']);
    }
}