<?php

declare(strict_types=1);

namespace App\Runtime\Console\Commands;

use App\Runtime\Diagnostics\HealthCheckAggregator;
use App\Runtime\Diagnostics\KernelSnapshotFactory;
use App\Runtime\Failure\Contracts\FailureReportingContract;
use App\Runtime\Failure\Enums\FailureKind;
use App\Runtime\Failure\Enums\FailureState;
use App\Runtime\Failure\ValueObjects\FailureRecord;
use Illuminate\Console\Command;

/**
 * php artisan temple:runtime — print runtime status table.
 *
 * Doctrine: every artisan command that the Runtime module exposes
 * follows the same shape: gather facts, render table, return
 * Command::SUCCESS (exit 0) or Command::FAILURE (exit 1) based on
 * whether the system is in a healthy state.
 *
 * Exits non-zero when any subsystem probe fails. Reports the failure
 * to the runtime FailureRouter so the failure is logged + classified
 * (NOT a CLI-specific path — it flows through the same membrane).
 */
final class RuntimeStatusCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'temple:runtime';

    /**
     * @var string
     */
    protected $description = 'Print runtime status table (PHP, Laravel, env, DB driver, cache, queue, app key, probe results).';

    public function __construct(
        private readonly HealthCheckAggregator $aggregator,
        private readonly KernelSnapshotFactory $snapshotFactory,
        private readonly FailureReportingContract $failureRouter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $snapshot = $this->snapshotFactory->capture();
        $results = $this->aggregator->probe();
        $allHealthy = $this->aggregator->allHealthy($results);

        $rows = [
            ['PHP',              $snapshot->phpVersion],
            ['Laravel',          $snapshot->laravelVersion],
            ['Env',              $snapshot->environment->label()],
            ['DB driver',        $snapshot->dbDriver],
            ['Cache store',      $snapshot->cacheStore],
            ['Queue driver',     $snapshot->queueDriver],
            ['App key',          $snapshot->appKeyPresent ? 'present' : 'MISSING'],
            ['Captured at',      $snapshot->capturedAt->format(\DateTimeImmutable::ATOM)],
        ];

        foreach ($results as $name => $result) {
            $status = $result->isHealthy() ? 'OK' : 'FAIL';
            $detail = $result->detail !== null ? " ({$result->detail})" : '';
            $rows[] = [ucfirst($name), "{$status}{$detail}"];
        }

        $this->table(['Subsystem', 'Status'], $rows);

        if (! $allHealthy) {
            // Surface the unhealthy-system failure to the runtime router
            // so it is logged + classified per the failure doctrine.
            $this->failureRouter->report(new FailureRecord(
                id: \App\Shared\ValueObjects\Identifier::generate(),
                kind: FailureKind::CommandFailed,
                origin: self::class,
                message: 'temple:runtime detected unhealthy subsystems',
                previousState: FailureState::Observed,
                context: ['command' => 'temple:runtime', 'unhealthy' => array_keys(array_filter($results, fn ($r) => ! $r->isHealthy()))],
                occurredAt: new \DateTimeImmutable(),
            ));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}