<?php

declare(strict_types=1);

namespace App\Persistence\Neon\Console;

use App\Persistence\Neon\Diagnostics\NeonDiagnosticsProbe;
use App\Persistence\Neon\ValueObjects\NeonConnectionConfig;
use App\Runtime\Diagnostics\HealthCheckResult;
use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use App\Shared\Support\Identifier;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * php artisan temple:neon:ping — deep Neon PostgreSQL introspection.
 *
 * Complements `temple:runtime` (generic subsystem health) with Neon-specific
 * details: connection metadata, SSL status, extensions, role, db version.
 * Operators use this for incident triage, role verification, and
 * post-deploy verification.
 *
 * Doctrine:
 *   - Reuses the existing `FailureReportingContract` for failure logging —
 *     no new failure path invented.
 *   - Output NEVER includes the password (NeonConnectionConfig enforces this).
 *   - Exit 0 if all checks OK; exit 1 if any probe fails.
 */
final class NeonPingCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'temple:neon:ping';

    /**
     * @var string
     */
    protected $description = 'Print Neon PostgreSQL connection metadata + extension/role/SSL diagnostics.';

    public function __construct(
        private readonly NeonConnectionConfig $config,
        private readonly NeonDiagnosticsProbe $probe,
        private readonly FailureReportingContract $failureRouter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $configRows = [
            ['Host',             $this->config->host ?? '(not set)'],
            ['Database',         $this->config->database ?? '(not set)'],
            ['Username',         $this->config->username ?? '(not set)'],
            ['Role',             $this->config->role->label()],
            ['SSL mode',         $this->config->sslmode ?? '(not set)'],
            ['Channel binding',  $this->config->channelBinding ?? '(not set)'],
            ['Application name', $this->config->applicationName ?? '(not set)'],
            ['Pooled (PgBouncer)', $this->config->isPooled ? 'yes' : 'no'],
            ['SSL required (≥require)', $this->config->hasSecureSslMode() ? 'yes' : 'no'],
        ];

        $this->info('Neon connection metadata:');
        $this->table(['Property', 'Value'], $configRows);

        $this->newLine();

        $result = $this->probe->probe();

        $this->info('Neon probe results:');
        $this->table(
            ['Probe', 'Status', 'Latency (ms)', 'Detail'],
            [
                [
                    $result->name,
                    $result->isHealthy() ? 'OK' : 'FAIL',
                    (string) round($result->latencyMs, 3),
                    $result->detail ?? '',
                ],
            ],
        );

        if (! $result->isHealthy()) {
            $this->newLine();
            $this->error(sprintf('Neon probe failed: %s', $result->detail ?? '(no detail)'));

            $this->failureRouter->report(new FailureRecord(
                id: Identifier::generate(),
                kind: FailureKind::ProbeSubsystemDown,
                origin: self::class,
                message: 'temple:neon:ping detected unhealthy Neon subsystem',
                previousState: FailureState::Observed,
                context: [
                    'command'       => 'temple:neon:ping',
                    'probe_name'    => $result->name,
                    'probe_detail'  => $result->detail,
                    'latency_ms'    => $result->latencyMs,
                ],
                occurredAt: new DateTimeImmutable(),
            ));

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('All Neon checks OK.');
        return self::SUCCESS;
    }
}