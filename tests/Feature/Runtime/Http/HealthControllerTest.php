<?php

declare(strict_types=1);

namespace Tests\Feature\Runtime\Http;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Runtime\Diagnostics\CacheHealthProbe;
use App\Runtime\Diagnostics\DatabaseHealthProbe;
use App\Runtime\Diagnostics\HealthCheckAggregator;
use App\Runtime\Diagnostics\HealthCheckResult;
use App\Runtime\Diagnostics\KernelSnapshotFactory;
use App\Runtime\Diagnostics\QueueHealthProbe;
use App\Runtime\Http\Controllers\HealthController;
use App\Runtime\Http\Controllers\PingController;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Contracts\EnvironmentContract;
use App\Shared\ValueObjects\Identifier;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Foundation\Application;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for the runtime HTTP controllers.
 *
 * Strategy: tag the three real probes for the HealthCheckAggregator
 * (RuntimeServiceProvider does this in Phase F; here we wire it inline
 * for the test). Then exercise the controllers via the real HTTP stack
 * ($this->get(...)) so middleware + response shape are validated end-to-end.
 */
final class HealthControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tag probes for the aggregator. RuntimeServiceProvider wires this in Phase F.
        $this->app->tag(
            [DatabaseHealthProbe::class, CacheHealthProbe::class, QueueHealthProbe::class],
            'runtime.health_probe',
        );
        $this->app->forgetInstance(HealthCheckAggregator::class);

        // Bind KernelSnapshotFactory explicitly (Phase F's provider wires it;
        // we replicate the wiring here for the test).
        $this->app->bind(KernelSnapshotFactory::class, function ($app) {
            return new KernelSnapshotFactory(
                $app->make(ConfigurationContract::class),
                $app->make(EnvironmentContract::class),
                DB::connection(),
                Cache::store(),
                Queue::connection()->getQueueManager() ?? app(QueueManager::class),
                $app->make(Application::class),
            );
        });
    }

    #[Test]
    public function ping_endpoint_returns_pong_with_v1(): void
    {
        $response = $this->get('/api/v1/ping');

        $response->assertOk();
        $response->assertJson(['ping' => 'pong', 'v' => '1']);
    }

    #[Test]
    public function health_endpoint_returns_200_when_all_probes_healthy(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'checked_at',
            'subsystems' => ['database', 'cache', 'queue'],
            'runtime' => ['php', 'laravel', 'env'],
        ]);
        $response->assertJsonPath('status', 'ok');
    }

    #[Test]
    public function health_endpoint_returns_503_when_a_probe_fails(): void
    {
        // Replace the persistence adapter with one that returns failure.
        $failing = new class implements PersistenceAdapterContract {
            public function connect(): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function disconnect(): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function isConnected(): bool { return true; }
            public function transaction(callable $callback): \App\Shared\Support\Result { return \App\Shared\Support\Result::success(null); }
            public function driver(): string { return 'sqlite'; }
            public function identifier(): Identifier { return Identifier::generate(); }
            public function query(string $sql, array $params = []): \App\Shared\Support\Result
            {
                return \App\Shared\Support\Result::failure('connection refused');
            }
            public function execute(string $sql, array $params = []): \App\Shared\Support\Result
            {
                return \App\Shared\Support\Result::success(0);
            }
        };
        $this->app->instance(PersistenceAdapterContract::class, $failing);
        $this->app->forgetInstance(DatabaseHealthProbe::class);
        $this->app->forgetInstance(HealthCheckAggregator::class);

        $response = $this->get('/health');

        $response->assertStatus(503);
        $response->assertJsonPath('status', 'degraded');
        $response->assertJsonPath('subsystems.database.status', 'fail');
        $response->assertJsonPath('subsystems.cache.status', 'ok');
        $response->assertJsonPath('subsystems.queue.status', 'ok');
    }

    #[Test]
    public function health_endpoint_includes_latency_for_each_subsystem(): void
    {
        $response = $this->get('/health');

        $response->assertOk();
        $body = $response->json();

        foreach (['database', 'cache', 'queue'] as $name) {
            $this->assertArrayHasKey($name, $body['subsystems']);
            $this->assertArrayHasKey('latency_ms', $body['subsystems'][$name]);
            $this->assertIsNumeric($body['subsystems'][$name]['latency_ms']);
        }
    }

    #[Test]
    public function ping_controller_resolves_with_no_dependencies(): void
    {
        $controller = $this->app->make(PingController::class);
        $this->assertInstanceOf(PingController::class, $controller);
    }

    #[Test]
    public function health_controller_resolves_from_container(): void
    {
        $controller = $this->app->make(HealthController::class);
        $this->assertInstanceOf(HealthController::class, $controller);
    }
}