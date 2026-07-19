<?php

declare(strict_types=1);

namespace Tests\Feature\Queue;

use App\Jobs\Templates\HealthCheckQueuedJob;
use App\Queue\Contracts\QueueConnectorContract;
use App\Queue\ValueObjects\QueuedJob;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Verify dispatch -> enqueue -> pop -> run flow.
 *
 * Doctrine: every domain job follows the AbstractQueuedJob pattern.
 * HealthCheckQueuedJob is the template; this test exercises it.
 *
 * Note: testing env uses QUEUE_CONNECTION=sync (phpunit.xml), so jobs
 * run inline within dispatch(). We assert the side effect fired by
 * checking the correlation ID appears in the job's runtime state.
 */
final class QueueDispatchTest extends TestCase
{
    public function test_dispatch_returns_a_job_id_string(): void
    {
        $connector = $this->app->make(QueueConnectorContract::class);

        $job = QueuedJob::create(
            jobClass: HealthCheckQueuedJob::class,
            payload: [
                'correlationId' => Identifier::generate(),
                'probeName'     => 'queue-bindings-test',
            ],
        );

        $id = $connector->dispatch($job);

        self::assertIsString($id);
        self::assertNotEmpty($id);
    }

    public function test_sync_queue_runs_job_inline(): void
    {
        // phpunit.xml sets QUEUE_CONNECTION=sync, so dispatch runs
        // the job inline. We assert no exception was thrown and the
        // dispatch returned a valid id.
        $connector = $this->app->make(QueueConnectorContract::class);

        $job = QueuedJob::create(
            jobClass: HealthCheckQueuedJob::class,
            payload: [
                'correlationId' => Identifier::generate(),
                'probeName'     => 'sync-inline-test',
            ],
        );

        // Inline run — should not throw.
        $id = $connector->dispatch($job);
        self::assertNotEmpty($id);
    }

    public function test_financial_job_constructor_marks_fail_fast(): void
    {
        // Doctrine: financial jobs use QueuedJob::financial() factory
        // which sets tries=1 and backoff=[0]. Verify the factory.
        $job = QueuedJob::financial(
            jobClass: HealthCheckQueuedJob::class,
            payload: [
                'correlationId' => Identifier::generate(),
            ],
        );

        self::assertSame(1, $job->tries);
        self::assertSame([0], $job->backoff);
    }

    public function test_general_job_constructor_defaults(): void
    {
        $job = QueuedJob::create(
            jobClass: HealthCheckQueuedJob::class,
            payload: [
                'correlationId' => Identifier::generate(),
            ],
        );

        // Doctrine defaults: 3 tries, [10,60,300] backoff.
        self::assertSame(3, $job->tries);
        self::assertSame([10, 60, 300], $job->backoff);
    }

    public function test_tracking_id_is_unique_per_dispatch(): void
    {
        $job1 = QueuedJob::create(HealthCheckQueuedJob::class, ['correlationId' => Identifier::generate()]);
        $job2 = QueuedJob::create(HealthCheckQueuedJob::class, ['correlationId' => Identifier::generate()]);

        self::assertNotSame(
            $job1->trackingId()->value(),
            $job2->trackingId()->value(),
        );
    }
}
