<?php

declare(strict_types=1);

namespace App\Queue\Console\Commands;

use App\Queue\Contracts\QueueConnectorContract;
use Illuminate\Console\Command;

/**
 * php artisan temple:queue:stats — dump queue substrate state.
 *
 * Doctrine: every operational artifact must have a corresponding
 * surface for ops to read without spelunking through code. This is
 * the ops surface; QueueHealthProbe is the health surface. They
 * overlap on purpose — health is automatic (probe-driven) and
 * stats is on-demand (this command).
 *
 * Doctrine rationale for splitting:
 *   - Health probe must NEVER throw — it observes. It returns
 *     HealthCheckResult.
 *   - Stats command CAN print errors — it reports. Exit code
 *     signals "investigate".
 */
final class QueueStatsCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'temple:queue:stats
        {--connection=default : Which queue connection to inspect}
        {--failed-limit=20 : Max failed jobs to list}';

    /**
     * @var string
     */
    protected $description = 'Print queue substrate state: driver, depth, failed count, oldest pending.';

    public function __construct(
        private readonly QueueConnectorContract $connector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $connection = (string) $this->option('connection');
        $failedLimit = (int) $this->option('failed-limit');

        $this->line('');
        $this->info("Queue substrate state");

        $rows = [
            ['Driver',         $this->connector->driver()],
            ['Reachable',      $this->connector->ping() ? 'YES' : 'NO'],
            ['Depth (default)', (string) $this->connector->size(null)],
            ['Failed (total)', (string) $this->connector->failedCount()],
        ];

        $this->table(['Metric', 'Value'], $rows);

        $failed = $this->connector->listFailed($failedLimit);
        if ($failed !== []) {
            $this->line('');
            $this->info("Failed jobs (newest " . count($failed) . "):");
            $tableRows = array_map(
                fn ($f) => [
                    substr($f['uuid'], 0, 8) . '…',
                    $f['queue'],
                    $f['connection'],
                    $f['failed_at'],
                    substr($f['exception_preview'], 0, 60) . '…',
                ],
                $failed,
            );
            $this->table(['UUID', 'Queue', 'Connection', 'Failed at', 'Exception preview'], $tableRows);
        } else {
            $this->line('');
            $this->info('No failed jobs.');
        }

        return self::SUCCESS;
    }
}
