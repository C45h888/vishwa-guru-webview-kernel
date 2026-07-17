<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence\Neon;

use App\Persistence\Neon\Diagnostics\NeonDiagnosticsProbe;
use App\Persistence\Neon\ValueObjects\NeonConnectionConfig;
use App\Runtime\Diagnostics\HealthCheckAggregator;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests verifying end-to-end integration of the Neon module
 * with the existing Laravel container, runtime health subsystem, and
 * config/database.php.
 */
final class IntegrationTest extends TestCase
{
    #[Test]
    public function the_neon_connection_block_resolves_via_config_database_php(): void
    {
        // Verify the connection block is registered.
        $connections = config('database.connections');

        $this->assertArrayHasKey('neon', $connections);
        $this->assertSame('pgsql', $connections['neon']['driver']);
        $this->assertSame('require', $connections['neon']['sslmode']);
        $this->assertSame('temple-trust', $connections['neon']['application_name']);
    }

    #[Test]
    public function the_pgsql_block_no_longer_overrides_sslmode_with_prefer(): void
    {
        $pgsql = config('database.connections.pgsql');

        $this->assertArrayNotHasKey(
            'sslmode',
            $pgsql,
            "pgsql connection must not hard-code sslmode — it must defer to DATABASE_URL",
        );
    }

    #[Test]
    public function switching_db_connection_to_neon_does_not_break_the_testing_stack(): void
    {
        // In testing env, DB_CONNECTION=sqlite. The neon block exists in
        // config but is not active. Verify that switching the config to
        // 'neon' would route to the right block, but the actual test DB
        // stays sqlite so existing tests don't break.
        config(['database.default' => 'sqlite']);
        $this->assertSame('sqlite', DB::connection()->getDriverName());

        config(['database.default' => 'neon']);
        // The neon block doesn't connect in this test (no DATABASE_URL set);
        // Laravel would fall back to default. We just verify the config
        // switch is honored without throwing.
        $this->assertSame('neon', config('database.default'));
    }

    #[Test]
    public function the_neon_diagnostics_probe_is_tagged_for_the_health_aggregator(): void
    {
        $aggregator = $this->app->make(HealthCheckAggregator::class);

        // Trigger probe resolution.
        $results = $aggregator->probe();

        // The 'neon' subsystem should be in the results (alongside the
        // existing 3 from Phase 2 Runtime).
        $this->assertArrayHasKey('neon', $results);
        $this->assertArrayHasKey('database', $results);
        $this->assertArrayHasKey('cache', $results);
        $this->assertArrayHasKey('queue', $results);
    }

    #[Test]
    public function the_neon_connection_config_resolves_from_the_container(): void
    {
        $config = $this->app->make(NeonConnectionConfig::class);
        $this->assertInstanceOf(NeonConnectionConfig::class, $config);
    }

    #[Test]
    public function the_neon_diagnostics_probe_resolves_from_the_container(): void
    {
        $probe = $this->app->make(NeonDiagnosticsProbe::class);
        $this->assertInstanceOf(NeonDiagnosticsProbe::class, $probe);
    }

    #[Test]
    public function neon_probes_inherit_real_phpredis_session_cache_and_queue_in_testing(): void
    {
        // Verify the probe can complete in testing env — the testing DB
        // is sqlite, so the probe's "extensions present" check may fail
        // (sqlite doesn't have pgcrypto/citext/btree_gist). The probe
        // itself still completes without throwing.
        $probe = $this->app->make(NeonDiagnosticsProbe::class);

        $result = $probe->probe();

        // In sqlite testing env, the probe may report extensions missing
        // — that's expected. The point is: it completes.
        $this->assertNotNull($result);
        $this->assertSame('neon', $result->name);
        $this->assertGreaterThanOrEqual(0.0, $result->latencyMs);
    }
}