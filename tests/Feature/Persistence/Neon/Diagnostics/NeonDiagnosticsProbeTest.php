<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence\Neon\Diagnostics;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\Neon\Diagnostics\NeonDiagnosticsProbe;
use App\Persistence\Neon\ValueObjects\NeonConnectionConfig;
use App\Persistence\Neon\ValueObjects\NeonRole;
use App\Runtime\Diagnostics\HealthCheckResult;
use App\Shared\Contracts\ConfigurationContract;
use App\Shared\Support\Result;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for NeonDiagnosticsProbe.
 *
 * Strategy: mock the PersistenceAdapterContract to control what the probe
 * sees. Real Neon is unreachable in CI; mocking is the only way to test
 * the probe's full lifecycle.
 */
final class NeonDiagnosticsProbeTest extends TestCase
{
    private NeonConnectionConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        // Real NeonConnectionConfig from container (constructor takes ConfigurationContract).
        $this->config = $this->app->make(NeonConnectionConfig::class);
    }

    /**
     * Build a fake PersistenceAdapterContract whose query() returns the
     * given rows or failure per query.
     */
    private function bindFakeAdapter(array $queryResponses): PersistenceAdapterContract
    {
        $callIndex = 0;
        $adapter = new class($queryResponses, $callIndex) implements PersistenceAdapterContract {
            /** @var array<int, array{0: bool, 1: array<int, array<string, mixed>>|string}> */
            private array $responses;
            private int $callIndex;

            /**
             * @param  array<int, array{0: bool, 1: array<int, array<string, mixed>>|string}>  $responses
             */
            public function __construct(array $responses, int &$callIndex)
            {
                $this->responses = $responses;
                $this->callIndex = &$callIndex;
            }

            public function connect(): Result { return Result::success(null); }
            public function disconnect(): Result { return Result::success(null); }
            public function isConnected(): bool { return true; }
            public function transaction(callable $callback): Result { return Result::success(null); }
            public function driver(): string { return 'pgsql'; }
            public function identifier(): \App\Shared\ValueObjects\Identifier { return \App\Shared\ValueObjects\Identifier::generate(); }

            public function query(string $sql, array $params = []): Result
            {
                if ($this->callIndex >= count($this->responses)) {
                    return Result::failure('no more mock responses');
                }
                $response = $this->responses[$this->callIndex++];
                if ($response[0]) {
                    return Result::success($response[1]);
                }
                return Result::failure((string) $response[1]);
            }

            public function execute(string $sql, array $params = []): Result
            {
                return Result::success(0);
            }

            public function connectionMetadata(): Result
            {
                return Result::success([
                    'driver' => 'pgsql',
                    'identifier' => 'fake',
                    'is_connected' => true,
                    'database' => 'neondb',
                    'host' => 'ep-fake.neon.tech',
                    'port' => 5432,
                    'username' => 'neondb_owner',
                    'application_name' => 'temple-trust',
                    'sslmode' => 'require',
                ]);
            }
        };

        $this->app->instance(PersistenceAdapterContract::class, $adapter);
        $this->app->forgetInstance(NeonDiagnosticsProbe::class);
        return $adapter;
    }

    #[Test]
    public function probe_name_is_neon(): void
    {
        $probe = $this->app->make(NeonDiagnosticsProbe::class);
        $this->assertSame('neon', $probe->name());
    }

    #[Test]
    public function probe_resolves_from_the_container(): void
    {
        $probe = $this->app->make(NeonDiagnosticsProbe::class);
        $this->assertInstanceOf(NeonDiagnosticsProbe::class, $probe);
    }

    #[Test]
    public function probe_returns_fail_when_reachability_check_fails(): void
    {
        $this->bindFakeAdapter([
            [false, 'connection refused'],
        ]);

        $probe = $this->app->make(NeonDiagnosticsProbe::class);
        $result = $probe->probe();

        $this->assertInstanceOf(HealthCheckResult::class, $result);
        $this->assertFalse($result->isHealthy());
        $this->assertSame('neon', $result->name);
        $this->assertStringContainsString('reachability', $result->detail ?? '');
        $this->assertStringContainsString('connection refused', $result->detail ?? '');
    }

    #[Test]
    public function probe_returns_fail_when_ssl_is_not_on(): void
    {
        $this->bindFakeAdapter([
            [true, [['one' => 1]]],   // SELECT 1 OK
            [true, [['ssl' => 'off']]], // SHOW ssl returns off
        ]);

        $probe = $this->app->make(NeonDiagnosticsProbe::class);
        $result = $probe->probe();

        $this->assertFalse($result->isHealthy());
        $this->assertStringContainsString('SSL', $result->detail ?? '');
    }

    #[Test]
    public function probe_returns_fail_when_a_required_extension_is_missing(): void
    {
        $this->bindFakeAdapter([
            [true, [['one' => 1]]],                          // SELECT 1 OK
            [true, [['ssl' => 'on']]],                       // SHOW ssl OK
            [true, [['role_name' => 'app_user']]],           // current_user OK
            [true, [['extname' => 'pgcrypto'], ['extname' => 'citext']]], // missing btree_gist
        ]);

        $probe = $this->app->make(NeonDiagnosticsProbe::class);
        $result = $probe->probe();

        $this->assertFalse($result->isHealthy());
        $this->assertStringContainsString('btree_gist', $result->detail ?? '');
    }

    #[Test]
    public function probe_returns_ok_when_all_checks_pass(): void
    {
        $this->bindFakeAdapter([
            [true, [['one' => 1]]],                                            // SELECT 1
            [true, [['ssl' => 'on']]],                                         // SHOW ssl
            [true, [['role_name' => 'app_user']]],                             // current_user
            [true, [['extname' => 'pgcrypto'], ['extname' => 'citext'], ['extname' => 'btree_gist']]], // all ext
            [true, [['server_version' => 'PostgreSQL 16.2']]],                 // SHOW server_version
        ]);

        $probe = $this->app->make(NeonDiagnosticsProbe::class);
        $result = $probe->probe();

        $this->assertTrue($result->isHealthy());
        $this->assertSame('neon', $result->name);
        $this->assertGreaterThanOrEqual(0.0, $result->latencyMs);
        $this->assertStringContainsString('role=app_user', $result->detail ?? '');
        $this->assertStringContainsString('ssl=on', $result->detail ?? '');
    }

    #[Test]
    public function probe_never_throws_even_when_adapter_throws(): void
    {
        $throwingAdapter = new class implements PersistenceAdapterContract {
            public function connect(): Result { return Result::success(null); }
            public function disconnect(): Result { return Result::success(null); }
            public function isConnected(): bool { return true; }
            public function transaction(callable $callback): Result { return Result::success(null); }
            public function driver(): string { return 'pgsql'; }
            public function identifier(): \App\Shared\ValueObjects\Identifier { return \App\Shared\ValueObjects\Identifier::generate(); }

            public function query(string $sql, array $params = []): Result
            {
                throw new \RuntimeException('boom');
            }
            public function execute(string $sql, array $params = []): Result
            {
                return Result::success(0);
            }

            public function connectionMetadata(): Result
            {
                return Result::success([
                    'driver' => 'pgsql',
                    'identifier' => 'fake',
                    'is_connected' => true,
                    'database' => 'neondb',
                    'host' => 'ep-fake.neon.tech',
                    'port' => 5432,
                    'username' => 'neondb_owner',
                    'application_name' => 'temple-trust',
                    'sslmode' => 'require',
                ]);
            }
        };

        $this->app->instance(PersistenceAdapterContract::class, $throwingAdapter);
        $this->app->forgetInstance(NeonDiagnosticsProbe::class);

        $probe = $this->app->make(NeonDiagnosticsProbe::class);

        // Should NOT throw.
        $result = $probe->probe();

        $this->assertFalse($result->isHealthy());
        $this->assertStringContainsString('boom', $result->detail ?? '');
    }
}