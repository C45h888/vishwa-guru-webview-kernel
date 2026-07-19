<?php

declare(strict_types=1);

namespace Tests\Feature\Persistence\Neon\Console;

use App\Persistence\Contracts\PersistenceAdapterContract;
use App\Persistence\Neon\Diagnostics\NeonDiagnosticsProbe;
use App\Persistence\Neon\ValueObjects\NeonConnectionConfig;
use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\StateMachines\FailureTransitionResult;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\Support\Result;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for `php artisan temple:neon:ping`.
 *
 * Strategy: bind a fake FailureReportingContract (Phase 2 contract; stubbed
 * here so the command can resolve without depending on the real router).
 * Bind a fake PersistenceAdapterContract that returns controlled responses.
 */
final class NeonPingCommandTest extends TestCase
{
    private object $fakeRouter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeRouter = new class implements FailureReportingContract {
            /** @var array<int, FailureRecord> */
            public array $reports = [];

            public function report(FailureRecord $record): FailureTransitionResult
            {
                $this->reports[] = $record;
                return new FailureTransitionResult(
                    nextState: FailureState::Resolved,
                    handlerClass: \App\Runtime\Failure\Handlers\ProbeFailureHandler::class,
                    logLevel: 'WARNING',
                    userMessage: 'stubbed',
                    coalesce: false,
                    coalesceWindowSeconds: 0,
                );
            }
        };
        $this->app->instance(FailureReportingContract::class, $this->fakeRouter);
    }

    /**
     * Build a fake PersistenceAdapterContract whose query() returns the
     * given responses in order. The probe issues 5 queries on the happy path
     * (SELECT 1, SHOW ssl, current_user, pg_extension, SHOW server_version).
     */
    private function bindFakeAdapter(array $responses): PersistenceAdapterContract
    {
        $callIndex = 0;
        $adapter = new class($responses, $callIndex) implements PersistenceAdapterContract {
            private array $responses;
            private int $callIndex;

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
                    return Result::success([]);
                }
                return $this->responses[$this->callIndex++];
            }

            public function execute(string $sql, array $params = []): Result
            {
                return Result::success(0);
            }

            public function connectionMetadata(): Result
            {
                return Result::success([
                    'driver' => 'pgsql', 'identifier' => 'fake', 'is_connected' => true,
                    'sslmode' => 'require', 'database' => 'neondb',
                    'application_name' => 'temple-trust',
                ]);
            }
        };

        $this->app->instance(PersistenceAdapterContract::class, $adapter);
        $this->app->forgetInstance(NeonDiagnosticsProbe::class);
        $this->app->forgetInstance(NeonPingCommand::class);
    }

    #[Test]
    public function the_command_prints_metadata_and_probe_results_on_healthy_neon(): void
    {
        $this->bindFakeAdapter([
            Result::success([['one' => 1]]),                                        // SELECT 1
            Result::success([['ssl' => 'on']]),                                     // SHOW ssl
            Result::success([['role_name' => 'app_user']]),                         // current_user
            Result::success([['extname' => 'pgcrypto'], ['extname' => 'citext'], ['extname' => 'btree_gist']]),
            Result::success([['server_version' => 'PostgreSQL 16.2']]),              // SHOW server_version
        ]);

        $exitCode = Artisan::call('temple:neon:ping');

        $this->assertSame(0, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('Neon connection metadata', $output);
        $this->assertStringContainsString('Host', $output);
        $this->assertStringContainsString('Role', $output);
        $this->assertStringContainsString('SSL mode', $output);
        $this->assertStringContainsString('Neon probe results', $output);
        $this->assertStringContainsString('OK', $output);
        $this->assertStringContainsString('All Neon checks OK', $output);
    }

    #[Test]
    public function the_command_exits_non_zero_when_probe_fails_and_reports_to_router(): void
    {
        $this->bindFakeAdapter([
            Result::failure('connection refused'),
        ]);

        $exitCode = Artisan::call('temple:neon:ping');

        $this->assertSame(1, $exitCode);

        $this->assertCount(1, $this->fakeRouter->reports);
        $this->assertSame(
            \App\Runtime\Failure\Enums\FailureKind::ProbeSubsystemDown,
            $this->fakeRouter->reports[0]->kind,
        );

        $output = Artisan::output();
        $this->assertStringContainsString('FAIL', $output);
        $this->assertStringContainsString('connection refused', $output);
    }

    #[Test]
    public function the_command_does_not_report_failure_on_healthy_neon(): void
    {
        $this->bindFakeAdapter([
            Result::success([['one' => 1]]),
            Result::success([['ssl' => 'on']]),
            Result::success([['role_name' => 'app_user']]),
            Result::success([['extname' => 'pgcrypto'], ['extname' => 'citext'], ['extname' => 'btree_gist']]),
            Result::success([['server_version' => 'PostgreSQL 16.2']]),
        ]);

        Artisan::call('temple:neon:ping');

        $this->assertCount(0, $this->fakeRouter->reports);
    }

    #[Test]
    public function the_command_class_resolves_from_the_container(): void
    {
        $command = $this->app->make(\App\Persistence\Neon\Console\NeonPingCommand::class);
        $this->assertInstanceOf(\App\Persistence\Neon\Console\NeonPingCommand::class, $command);
    }

    #[Test]
    public function the_command_never_includes_password_in_output(): void
    {
        $this->bindFakeAdapter([
            Result::success([['one' => 1]]),
            Result::success([['ssl' => 'on']]),
            Result::success([['role_name' => 'app_user']]),
            Result::success([['extname' => 'pgcrypto'], ['extname' => 'citext'], ['extname' => 'btree_gist']]),
            Result::success([['server_version' => 'PostgreSQL 16.2']]),
        ]);

        // Inject a NeonConnectionConfig that has password-like content via DATABASE_URL.
        config([
            'database.connections.neon.url' => 'postgresql://app_user:supersecret_pw_xyz@ep-foo.neon.tech/neondb?sslmode=require',
        ]);
        $this->app->forgetInstance(NeonConnectionConfig::class);
        $this->app->forgetInstance(\App\Persistence\Neon\Console\NeonPingCommand::class);

        Artisan::call('temple:neon:ping');
        $output = Artisan::output();

        // Password must NEVER appear in command output.
        $this->assertStringNotContainsString('supersecret_pw_xyz', $output);
        $this->assertStringNotContainsString('s3cret', $output);
    }
}