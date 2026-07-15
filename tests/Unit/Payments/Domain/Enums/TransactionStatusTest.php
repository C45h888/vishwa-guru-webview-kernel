<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\Enums;

use App\Payments\Domain\Enums\TransactionStatus;
use PHPUnit\Framework\TestCase;

class TransactionStatusTest extends TestCase
{
    public function testTerminalStates(): void
    {
        $this->assertTrue(TransactionStatus::SETTLED->isTerminal());
        $this->assertTrue(TransactionStatus::FAILED->isTerminal());
        $this->assertTrue(TransactionStatus::REFUNDED->isTerminal());
        $this->assertTrue(TransactionStatus::CANCELLED->isTerminal());
        $this->assertTrue(TransactionStatus::EXPIRED->isTerminal());

        $this->assertFalse(TransactionStatus::PENDING->isTerminal());
        $this->assertFalse(TransactionStatus::CAPTURED->isTerminal());
    }

    public function testSuccessfulStates(): void
    {
        $this->assertTrue(TransactionStatus::CAPTURED->isSuccessful());
        $this->assertTrue(TransactionStatus::SETTLED->isSuccessful());
        $this->assertTrue(TransactionStatus::SETTLING->isSuccessful());

        $this->assertFalse(TransactionStatus::FAILED->isSuccessful());
        $this->assertFalse(TransactionStatus::PENDING->isSuccessful());
    }

    public function testRequiresReconciliation(): void
    {
        $this->assertTrue(TransactionStatus::AUTHORIZED->requiresReconciliation());
        $this->assertTrue(TransactionStatus::CAPTURED->requiresReconciliation());
        $this->assertFalse(TransactionStatus::FAILED->requiresReconciliation());
    }

    public function testCanBeRefunded(): void
    {
        $this->assertTrue(TransactionStatus::CAPTURED->canBeRefunded());
        $this->assertTrue(TransactionStatus::SETTLED->canBeRefunded());
        $this->assertFalse(TransactionStatus::FAILED->canBeRefunded());
        $this->assertFalse(TransactionStatus::PENDING->canBeRefunded());
    }

    public function testLabels(): void
    {
        $this->assertSame('Captured', TransactionStatus::CAPTURED->label());
        $this->assertSame('Partially Refunded', TransactionStatus::PARTIALLY_REFUNDED->label());
    }
}