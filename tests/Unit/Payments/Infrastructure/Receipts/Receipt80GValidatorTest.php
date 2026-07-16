<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Infrastructure\Receipts;

use App\Payments\Domain\Enums\Currency;
use App\Payments\Infrastructure\Receipts\Receipt80GValidator;
use App\Shared\Contracts\ConfigurationContract;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class Receipt80GValidatorTest extends TestCase
{
    private Receipt80GValidator $validator;
    private ConfigurationContract $config;

    protected function setUp(): void
    {
        $this->config = $this->createMock(ConfigurationContract::class);
        $this->validator = new Receipt80GValidator($this->config);
    }

    public function testIneligibleWhenTrustNotRegistered(): void
    {
        $this->config->method('boolean')->willReturn(false);

        $result = $this->validator->isEligible(
            pan: 'ABCTY1234D',
            amountMinor: 10_000_00,
            currency: Currency::INR,
            paymentDate: new DateTimeImmutable(),
        );

        $this->assertTrue($result->isOk());
        $data = $result->value();
        $this->assertFalse($data['eligible']);
        $this->assertStringContainsString('not registered', $data['reason']);
    }

    public function testIneligibleWhenCurrencyIsNotInr(): void
    {
        $this->config->method('boolean')->willReturn(true);

        $result = $this->validator->isEligible(
            pan: 'ABCTY1234D',
            amountMinor: 10_000_00,
            currency: Currency::USD,
            paymentDate: new DateTimeImmutable(),
        );

        $this->assertTrue($result->isOk());
        $data = $result->value();
        $this->assertFalse($data['eligible']);
        $this->assertStringContainsString('INR', $data['reason']);
    }

    public function testIneligibleWhenPanMissingAndAboveThreshold(): void
    {
        $this->config->method('boolean')->willReturn(true);
        $this->config->method('integer')->willReturn(500_00); // ₹500 threshold

        $result = $this->validator->isEligible(
            pan: null,
            amountMinor: 600_00, // above ₹500
            currency: Currency::INR,
            paymentDate: new DateTimeImmutable(),
        );

        $this->assertTrue($result->isOk());
        $data = $result->value();
        $this->assertFalse($data['eligible']);
        $this->assertStringContainsString('PAN', $data['reason']);
    }

    public function testIneligibleWhenPanInvalid(): void
    {
        $this->config->method('boolean')->willReturn(true);
        $this->config->method('integer')->willReturn(500_00);

        $result = $this->validator->isEligible(
            pan: 'INVALIDPAN',
            amountMinor: 600_00,
            currency: Currency::INR,
            paymentDate: new DateTimeImmutable(),
        );

        $this->assertTrue($result->isOk());
        $data = $result->value();
        $this->assertFalse($data['eligible']);
        $this->assertStringContainsString('valid format', $data['reason']);
    }

    public function testEligibleWhenAllConditionsMet(): void
    {
        $this->config->method('boolean')->willReturn(true);
        $this->config->method('integer')->willReturn(500_00);
        $this->config->method('get')->willReturn('REG1234');

        $result = $this->validator->isEligible(
            pan: 'ABCTY1234D',
            amountMinor: 1_000_00, // ₹1,000
            currency: Currency::INR,
            paymentDate: new DateTimeImmutable('2026-05-15'),
        );

        $this->assertTrue($result->isOk());
        $data = $result->value();
        $this->assertTrue($data['eligible']);
        $this->assertNull($data['reason']);
    }

    public function testCertificateRequiredOnlyAboveThreshold(): void
    {
        $this->config->method('integer')->willReturn(500_00);

        // Below threshold — no cert needed
        $this->assertFalse(
            $this->validator->certificateRequired(400_00, hasPan: true),
        );

        // At threshold — no cert needed
        $this->assertFalse(
            $this->validator->certificateRequired(500_00, hasPan: true),
        );

        // Above threshold — cert needed
        $this->assertTrue(
            $this->validator->certificateRequired(501_00, hasPan: true),
        );
    }

    public function testCertificateRequiredWithoutPanIsHigher(): void
    {
        // Without PAN, cert threshold is ₹2,000
        $this->assertFalse(
            $this->validator->certificateRequired(200_00, hasPan: false),
        );
        $this->assertTrue(
            $this->validator->certificateRequired(201_00, hasPan: false),
        );
    }

    public function testCertificateNumberGeneratedWhenEligibleAndAboveThreshold(): void
    {
        $this->config->method('boolean')->willReturn(true);
        $this->config->method('integer')->willReturn(500_00);
        $this->config->method('get')->willReturn('REG1234');

        $result = $this->validator->isEligible(
            pan: 'ABCTY1234D',
            amountMinor: 10_000_00, // ₹10,000 — well above threshold
            currency: Currency::INR,
            paymentDate: new DateTimeImmutable('2026-05-15'),
        );

        $this->assertTrue($result->isOk());
        $data = $result->value();
        $this->assertTrue($data['eligible']);
        $this->assertTrue($data['certificate_required']);
        $this->assertNotNull($data['certificate_number']);
        $this->assertStringStartsWith('80G/', $data['certificate_number']);
    }
}
