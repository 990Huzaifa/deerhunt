<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:release-annual-credit')->everyMinute();
        $schedule->command('app:execute-bot-activity')->everyFifteenMinutes();
        $schedule->command('app:track-subscription')->everyMinute();
        // $schedule->command('app:track-premium')->everyMinute();
        $schedule->command('app:reset-credit')->everyMinute();
        $schedule->command('app:aggregate-geo-stats')->everyMinute();
        $schedule->command('app:reset-bot-daily-limit')->daily();
        // $schedule->command('backup:database')->daily();
        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
