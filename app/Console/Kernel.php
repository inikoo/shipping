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
        Commands\CreateUser::class,
        Commands\CreateShipper::class,
        Commands\CreateTenant::class,
        Commands\UpgradeShipment::class,
        Commands\UpdateEvents::class,

    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('update:events')->timezone('Europe/London')->everyThirtyMinutes()->between('8:00', '20:00');
        $schedule->command('update:events')->timezone('Europe/London')->everyThreeHours()->unlessBetween('8:00', '20:00');

    }
}
