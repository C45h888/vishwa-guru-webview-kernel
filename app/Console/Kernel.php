<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Register console commands.
     *
     * @var array<int, class-string<Command>>
     */
    protected $commands = [];

    /**
     * Define the application's command schedule.
     *
     * Phase 2: dumps Redis INFO to the log every minute so ops can see
     * memory pressure, connection count, and keyspace growth without
     * needing to ssh into the Redis box. Doctrine: prefer pulling
     * diagnostics into the application log over exposing Redis CLI
     * to operators.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('temple:redis:info --all-connections --keyspace')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground()
            ->appendOutputTo(storage_path('logs/redis-info.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/../../routes');
    }
}
