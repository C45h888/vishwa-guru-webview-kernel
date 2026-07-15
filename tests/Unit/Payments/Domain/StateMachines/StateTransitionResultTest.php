<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Domain\StateMachines;

use App\Payments\Domain\Enums\TransactionStatus;
use App\Payments\Domain\StateMachines\StateTransitionResult;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class StateTransitionResultTest extends TestCase
{
    public function testConstruction(): void
    {
        $now = new DateTimeImmutable();
        $result = new StateTransitionResult(
            toState: TransactionStatus::CAPTURED,
            entityChanges: ['status' => 'captured'],
            timestampChanges: ['captured_at' => $now],
            metadata: [],
        );

        $this->assertSame(TransactionStatus::CAPTURED, $result->toState());
        $this->assertSame(['status' => 'captured'], $result->entityChanges());
        $this->assertSame(['captured_at' => $now], $result->timestampChanges());
        $this->assertSame([], $result->metadata());
    }

    public function testEmptyChangeSetIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new StateTransitionResult(
            toState: TransactionStatus::CAPTURED,
            entityChanges: [],
            timestampChanges: [],
        );
    }

    public function testUpdatesTimestamp(): void
    {
        $now = new DateTimeImmutable();
        $result = new StateTransitionResult(
            toState: TransactionStatus::CAPTURED,
            entityChanges: ['status' => 'captured'],
            timestampChanges: ['captured_at' => $now],
        );

        $this->assertTrue($result->updatesTimestamp('captured_at'));
        $this->assertFalse($result->updatesTimestamp('settled_at'));
    }

    public function testTimestampFor(): void
    {
        $now = new DateTimeImmutable();
        $result = new StateTransitionResult(
            toState: TransactionStatus::CAPTURED,
            entityChanges: ['status' => 'captured'],
            timestampChanges: ['captured_at' => $now],
        );

        $this->assertSame($now, $result->timestampFor('captured_at'));
        $this->assertNull($result->timestampFor('settled_at'));
    }

    public function testPrimaryEntityChange(): void
    {
        $result = new StateTransitionResult(
            toState: TransactionStatus::CAPTURED,
            entityChanges: [
                'status' => 'captured',
                'amount_captured_minor' => 10000,
                'fee_minor' => 200,
            ],
        );

        $this->assertCount(3, $result->primaryEntityChange());
    }
}
