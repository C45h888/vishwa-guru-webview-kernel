<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Enums;

use App\Shared\Enums\EnvironmentType;
use App\Shared\Enums\LifecycleStage;
use PHPUnit\Framework\TestCase;

class EnumsTest extends TestCase
{
    public function testEnvironmentTypeLabels(): void
    {
        $this->assertSame('Local', EnvironmentType::Local->label());
        $this->assertSame('Production', EnvironmentType::Production->label());
        $this->assertSame('Testing', EnvironmentType::Testing->label());
        $this->assertSame('CI', EnvironmentType::CI->label());
    }

    public function testEnvironmentTypeHelpers(): void
    {
        $this->assertTrue(EnvironmentType::Production->isProduction());
        $this->assertFalse(EnvironmentType::Local->isProduction());
        $this->assertTrue(EnvironmentType::Local->isDevelopment());
        $this->assertTrue(EnvironmentType::CI->isDevelopment());
        $this->assertTrue(EnvironmentType::Testing->isTesting());
    }

    public function testLifecycleStageLabels(): void
    {
        $this->assertSame('Pre-Foundation', LifecycleStage::PreFoundation->label());
        $this->assertSame('Database Architecture', LifecycleStage::DatabaseArchitecture->label());
        $this->assertSame('Financial Platform', LifecycleStage::FinancialPlatform->label());
    }

    public function testLifecycleStagePhaseNumbers(): void
    {
        $this->assertSame(0.0, LifecycleStage::Constitution->phaseNumber());
        $this->assertSame(0.25, LifecycleStage::PreFoundation->phaseNumber());
        $this->assertSame(0.5, LifecycleStage::DatabaseArchitecture->phaseNumber());
        $this->assertSame(1.0, LifecycleStage::FinancialPlatform->phaseNumber());
        $this->assertSame(3.0, LifecycleStage::PublicPlatform->phaseNumber());
    }

    public function testLifecycleStageValues(): void
    {
        $this->assertSame('constitution', LifecycleStage::Constitution->value);
        $this->assertSame('pre_foundation', LifecycleStage::PreFoundation->value);
        $this->assertSame('financial_platform', LifecycleStage::FinancialPlatform->value);
    }
}