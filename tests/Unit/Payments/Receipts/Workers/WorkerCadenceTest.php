<?php

declare(strict_types=1);

namespace Tests\Unit\Payments\Receipts\Workers;

use App\Payments\Receipts\Workers\WorkerCadence;
use App\Payments\Receipts\Workers\WorkerExhaustedException;
use PHPUnit\Framework\TestCase;

/**
 * WorkerCadenceTest — the timeout + retry policy every receipt worker
 * runs under.
 */
final class WorkerCadenceTest extends TestCase
{
    public function testReturnsStageResultOnFirstSuccess(): void
    {
        $cadence = new WorkerCadence([
            'data' => ['timeout_ms' => 1000, 'max_attempts' => 3],
            'backoff_ms' => [1],
        ]);

        $calls = 0;
        $result = $cadence->run('data', function () use (&$calls): string {
            $calls++;

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame(1, $calls);
    }

    public function testRetriesUntilSuccess(): void
    {
        $cadence = new WorkerCadence([
            'types' => ['timeout_ms' => 1000, 'max_attempts' => 3],
            'backoff_ms' => [1, 1],
        ]);

        $calls = 0;
        $result = $cadence->run('types', function () use (&$calls): string {
            $calls++;
            if ($calls < 3) {
                throw new \RuntimeException('transient');
            }

            return 'ok';
        });

        self::assertSame('ok', $result);
        self::assertSame(3, $calls);
    }

    public function testThrowsWhenAttemptsExhausted(): void
    {
        $cadence = new WorkerCadence([
            'design' => ['timeout_ms' => 1000, 'max_attempts' => 2],
            'backoff_ms' => [1],
        ]);

        $calls = 0;

        try {
            $cadence->run('design', function () use (&$calls): never {
                $calls++;
                throw new \RuntimeException('always fails');
            });
            self::fail('expected WorkerExhaustedException');
        } catch (WorkerExhaustedException $e) {
            self::assertSame('design', $e->worker);
            self::assertSame(2, $e->attempts);
            self::assertSame(2, $calls);
            self::assertStringContainsString('always fails', (string) $e->lastError);
        }
    }

    public function testBudgetStopsFurtherAttempts(): void
    {
        // Budget is 1 attempt's worth of wall clock; the first attempt
        // burns it and no second attempt may start.
        $cadence = new WorkerCadence([
            'data' => ['timeout_ms' => 1, 'max_attempts' => 5],
            'backoff_ms' => [0],
        ]);

        $calls = 0;

        try {
            $cadence->run('data', function () use (&$calls): never {
                $calls++;
                usleep(5000); // 5ms > 1ms budget
                throw new \RuntimeException('slow failure');
            });
            self::fail('expected WorkerExhaustedException');
        } catch (WorkerExhaustedException $e) {
            self::assertSame(1, $calls, 'no further attempts may start after the budget is burned');
        }
    }

    public function testSlowSuccessCountsAsTimeout(): void
    {
        $cadence = new WorkerCadence([
            'data' => ['timeout_ms' => 1, 'max_attempts' => 2],
            'backoff_ms' => [0],
        ]);

        try {
            $cadence->run('data', function (): string {
                usleep(5000);

                return 'too late';
            });
            self::fail('expected WorkerExhaustedException');
        } catch (WorkerExhaustedException $e) {
            self::assertStringContainsString('exceeded budget', (string) $e->lastError);
        }
    }
}
