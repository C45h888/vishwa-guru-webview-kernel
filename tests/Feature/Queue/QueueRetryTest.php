<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Jobs\AbstractQueuedJob;
use App\Queue\ValueObjects\QueuedJob;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verify retry policy surfaces in AbstractQueuedJob subclasses.
 *
 * Doctrine: per-class retry policy is explicit. AbstractQueuedJob
 * provides $tries, $backoff, $queue, $timeout properties. Subclasses
 * override as needed. Financial jobs set $tries=1, $backoff=[0].
 *
 * Note: this test does NOT actually exercise the worker's retry
 * mechanism (would require running `queue:work` as a subprocess).
 * It pins the retry contract at the class-property level so the
 * doctrine stays enforced.
 */
final class QueueRetryTest extends TestCase
{
    public function test_abstract_queued_job_defaults_are_doctrine_correct(): void
    {
        $instance = new class extends AbstractQueuedJob {
            public function handle(): void {}
        };

        self::assertSame(3, $instance->tries);
        self::assertSame([10, 60, 300], $instance->backoff);
    }

    public function test_queued_job_factory_creates_general_default(): void
    {
        $job = QueuedJob::create('SomeJob', []);
        self::assertSame(3, $job->tries);
        self::assertSame([10, 60, 300], $job->backoff);
    }

    public function test_queued_job_factory_creates_financial_failfast(): void
    {
        $job = QueuedJob::financial('SomeJob', []);
        self::assertSame(1, $job->tries);
        self::assertSame([0], $job->backoff);
    }

    public function test_queue_general_block_in_config(): void
    {
        $general = config('queue.general');
        self::assertSame(3, $general['tries']);
        self::assertSame([10, 60, 300], $general['backoff']);
    }

    public function test_queue_financial_block_in_config(): void
    {
        $financial = config('queue.financial');
        self::assertSame(1, $financial['tries']);
    }

    public function test_queue_prune_block_in_config(): void
    {
        $prune = config('queue.prune');
        self::assertGreaterThan(0, $prune['failed_after_hours']);
        // Doctrine: 30 days = 720 hours is the recommended retention.
        self::assertSame(720, $prune['failed_after_hours']);
    }

    public function test_redis_connection_name_in_config(): void
    {
        $redis = config('database.redis');
        // The 'queue' logical DB must exist on Redis DB 2.
        self::assertArrayHasKey('queue', $redis);
        self::assertSame(2, $redis['queue']['database']);
    }
}
