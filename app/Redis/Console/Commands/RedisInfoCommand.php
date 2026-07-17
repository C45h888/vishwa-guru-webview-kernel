<?php

declare(strict_types=1);

namespace App\Redis\Console\Commands;

use App\Redis\Contracts\RedisConnectorContract;
use Illuminate\Console\Command;
use Throwable;

/**
 * php artisan temple:redis:info — dump Redis server info + key stats.
 *
 * Doctrine: every operational artifact must have a corresponding surface
 * for ops to read without spelunking through code. temple:runtime tells
 * you whether Redis is reachable (probe); temple:redis:info tells you
 * what's going on inside Redis (memory, clients, keyspace, slow log).
 *
 * Runs out-of-band via Laravel's scheduler — see app/Console/Kernel.php.
 * Also runnable on demand:
 *   php artisan temple:redis:info
 *   php artisan temple:redis:info --connection=cache
 *   php artisan temple:redis:info --all-connections
 *
 * Why a separate command and not folded into temple:runtime:
 *   - temple:runtime is the "is it healthy?" check. Quick, probe-shaped.
 *   - temple:redis:info is the "what is Redis doing?" check. Slower,
 *     verbose, diagnostic. Different cadence (per-minute vs on-demand).
 */
final class RedisInfoCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'temple:redis:info
        {--connection=default : Which Redis connection to inspect (default/cache/queue/session)}
        {--all-connections : Show info for every configured Redis connection}
        {--keyspace : Show keyspace stats (db size, keys, expires)}
        {--memory : Show memory stats (used_memory, peak, fragmentation)}';

    /**
     * @var string
     */
    protected $description = 'Dump Redis INFO + keyspace stats for one or all configured connections.';

    public function __construct(
        private readonly RedisConnectorContract $connector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $names = $this->option('all-connections')
            ? array_keys($this->connector->configuredDatabases())
            : [(string) $this->option('connection')];

        $allOk = true;

        foreach ($names as $name) {
            $ok = $this->reportConnection($name);
            $allOk = $allOk && $ok;
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    private function reportConnection(string $name): bool
    {
        $this->line('');
        $this->info("Redis connection: {$name}");

        if (! $this->connector->ping($name)) {
            $this->error("  [FAIL] connection '{$name}' is unreachable (timeout, refused, or auth failure)");

            return false;
        }

        $this->line('  [OK]   PING returned PONG');

        try {
            $client = $this->connector->connection($name);

            // phpredis INFO returns an associative array when called with
            // a section name; otherwise a flat string.
            if (method_exists($client, 'info')) {
                $info = $client->info();
            } else {
                $this->warn('  [WARN] underlying client does not implement info()');

                return true;
            }

            $rows = [];
            $rows[] = ['redis_version',   $info['redis_version']   ?? 'unknown'];
            $rows[] = ['uptime_in_seconds', $info['uptime_in_seconds'] ?? 'unknown'];
            $rows[] = ['connected_clients', $info['connected_clients'] ?? 'unknown'];
            $rows[] = ['tcp_port',        $info['tcp_port']        ?? 'unknown'];
            $rows[] = ['role',            $info['role']            ?? 'unknown'];

            if ($this->option('memory') || ! $this->option('keyspace')) {
                $rows[] = ['used_memory_human',     $info['used_memory_human']     ?? 'unknown'];
                $rows[] = ['used_memory_peak_human', $info['used_memory_peak_human'] ?? 'unknown'];
                $rows[] = ['mem_fragmentation_ratio', $info['mem_fragmentation_ratio'] ?? 'unknown'];
            }

            $this->table(['Metric', 'Value'], $rows);

            if ($this->option('keyspace') || ! $this->option('memory')) {
                $dbKeys = [];
                foreach ($info as $key => $value) {
                    if (str_starts_with((string) $key, 'db')) {
                        $dbKeys[] = [(string) $key, (string) $value];
                    }
                }
                if ($dbKeys !== []) {
                    $this->line('');
                    $this->line('  Keyspace:');
                    $this->table(['Database', 'Stats'], $dbKeys);
                }
            }
        } catch (Throwable $e) {
            $this->error("  [FAIL] info() threw: {$e->getMessage()}");

            return false;
        }

        return true;
    }
}
