<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\RouteListCommand::class,
        Commands\WarmUpFeedCacheCommand::class,
        Commands\WebSocketServerCommand::class,
        Commands\QueueWorkCommand::class,
        Commands\ProcessDialogCounterSagasCommand::class,
        Commands\ReconcileDialogCountersCommand::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('dialog:saga:process --limit=500')->everyMinute()->withoutOverlapping();
        $schedule->command('dialog:counters:reconcile --limit=100')->everyFiveMinutes()->withoutOverlapping();
    }
}
