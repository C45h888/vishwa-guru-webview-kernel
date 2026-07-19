<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Diagnostics;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Runtime\Diagnostics\CacheHealthProbe;
use App\Runtime\Diagnostics\DatabaseHealthProbe;
use App\Runtime\Diagnostics\HealthCheckAggregator;
use App\Runtime\Diagnostics\HealthCheckResult;
use App\Runtime\Diagnostics\HealthProbe;
use App\Runtime\Diagnostics\QueueHealthProbe;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the three concrete HealthProbe implementations +
 * the HealthCheckAggregator.
 *
 * Strategy:
 *   - Each probe is resolved from the container (wired singleton via
 *     RuntimeServiceProvider's tag binding — registered manually here
 *     since RuntimeServiceProvider is Phase F's deliverable).
 *   - Each probe is exercised against the real testing environment
 *     (sqlite :memory:, array cache, sync queue).
 *   - The data-provider loop covers all 3 probes in one assertion.
 *
 * Tests for failure injection (mock a probe to return fail()) live in
 * tests/Feature/Runtime/Http/HealthControllerTest.php.
 */
final class HealthProbeTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string<HealthProbe>, 1: string}>
     */
    public static function probeProvider(): array
    {
        return [
            'database' => [DatabaseHealthProbe::class, 'database'],
            'cache'    => [CacheHealthProbe::class, 'cache'],
            'queue'    => [QueueHealthProbe::class, 'queue'],
        ];
    }

    #[Test]
    #[DataProvider('probeProvider')]
    public function each_probe_returns_a_health_check_result_with_positive_latency(
        string $probeClass,
        string $expectedName,
    ): void {
        $probe = $this->app->make($probeClass);

        $result = $probe->probe();

        $this->assertInstanceOf(HealthCheckResult::class, $result);
        $this->assertSame($expectedName, $result->name);
        $this->assertGreaterThanOrEqual(0.0, $result->latencyMs);
    }

    #[Test]
    #[DataProvider('probeProvider')]
    public function each_probe_returns_ok_in_the_testing_environment(
        string $probeClass,
        string $expectedName,
    ): void {
        $probe = $this->app->make($probeClass);

        $result = $probe->probe();

        $this->assertTrue(
            $result->isHealthy(),
            "Probe [{$expectedName}] should be healthy in testing env but failed: "
                . ($result->detail ?? '(no detail)'),
        );
    }

    #[Test]
    public function database_probe_uses_persistence_adapter_contract_via_container(): void
    {
        // Replace the persistence adapter with a spy that records queries.
        $spy = new class implements PersistenceAdapterContract {
            /** @var array<int, string> */
            public array $queries = [];

            public function connect(): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function disconnect(): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function isConnected(): bool { return true; }
            public function transaction(callable $callback): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function driver(): string { return 'sqlite'; }
            public function identifier(): \App\Shared\ValueObjects\Identifier { return \App\Shared\ValueObjects\Identifier::generate(); }

            public function query(string $sql, array $params = []): \App\Shared\Support\Result
            {
                $this->queries[] = $sql;
                return \App\Shared\Support\Result::success([['one' => 1]]);
            }

            public function execute(string $sql, array $params = []): \App\Shared\Support\Result
            {
                return \App\Shared\Support\Result::success(0);
            }
            public function connectionMetadata(): \App\Shared\Support\Result
            {
                return \App\Shared\Support\Result::success([
                    'driver' => 'sqlite', 'identifier' => 'spy', 'is_connected' => true,
                ]);
            }
        };

        $this->app->instance(PersistenceAdapterContract::class, $spy);

        // Rebuild DatabaseHealthProbe against the spy.
        $this->app->forgetInstance(DatabaseHealthProbe::class);
        $probe = $this->app->make(DatabaseHealthProbe::class);

        $result = $probe->probe();

        $this->assertTrue($result->isHealthy());
        $this->assertCount(1, $spy->queries);
        $this->assertStringContainsString('SELECT 1', strtoupper($spy->queries[0]));
    }

    #[Test]
    public function database_probe_returns_fail_when_persistence_query_fails(): void
    {
        $failingAdapter = new class implements PersistenceAdapterContract {
            public function connect(): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function disconnect(): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function isConnected(): bool { return true; }
            public function transaction(callable $callback): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function driver(): string { return 'sqlite'; }
            public function identifier(): \App\Shared\ValueObjects\Identifier { return \App\Shared\ValueObjects\Identifier::generate(); }

            public function query(string $sql, array $params = []): \App\Shared\Support\Result
            {
                return \App\Shared\Support\Result::failure('connection refused');
            }

            public function execute(string $sql, array $params = []): \App\Shared\Support\Result
            {
                return \App\Shared\Support\Result::success(0);
            }
            public function connectionMetadata(): \App\Shared\Support\Result
            {
                return \App\Shared\Support\Result::success([
                    'driver' => 'sqlite', 'identifier' => 'failing', 'is_connected' => false,
                ]);
            }
        };

        $this->app->instance(PersistenceAdapterContract::class, $failingAdapter);
        $this->app->forgetInstance(DatabaseHealthProbe::class);
        $probe = $this->app->make(DatabaseHealthProbe::class);

        $result = $probe->probe();

        $this->assertFalse($result->isHealthy());
        $this->assertSame('database', $result->name);
        $this->assertStringContainsString('connection refused', $result->detail ?? '');
    }

    #[Test]
    public function aggregator_returns_results_for_each_tagged_probe(): void
    {
        // Manually tag probes for this test (RuntimeServiceProvider wires this in Phase F).
        $this->app->tag([DatabaseHealthProbe::class, CacheHealthProbe::class, QueueHealthProbe::class], 'runtime.health_probe');
        $this->app->forgetInstance(HealthCheckAggregator::class);

        $aggregator = $this->app->make(HealthCheckAggregator::class);
        $results = $aggregator->probe();

        $this->assertArrayHasKey('database', $results);
        $this->assertArrayHasKey('cache', $results);
        $this->assertArrayHasKey('queue', $results);
        $this->assertCount(3, $results);
        $this->assertTrue($aggregator->allHealthy($results));
    }

    #[Test]
    public function aggregator_allHealthy_returns_false_when_any_probe_fails(): void
    {
        $healthy = HealthCheckResult::ok('a', 1.0);
        $unhealthy = HealthCheckResult::fail('b', 1.0, 'broken');

        $aggregator = new HealthCheckAggregator($this->app);

        $this->assertTrue($aggregator->allHealthy(['a' => $healthy, 'b' => $healthy]));
        $this->assertFalse($aggregator->allHealthy(['a' => $healthy, 'b' => $unhealthy]));
        $this->assertTrue($aggregator->allHealthy([]));
    }
}