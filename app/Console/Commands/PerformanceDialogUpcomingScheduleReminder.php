<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\PerformanceDialogCronController;

class PerformanceDialogUpcomingScheduleReminder extends Command
{
    protected $signature = "app:performanceDialog:upcomingSchedule";
    protected $description = "Performance Dialog Upcoming Schedule";

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $controller = new PerformanceDialogCronController();
        $controller->upcomingScheduleReminder();
        $this->info("Email reminder sent successfully.");
    }
}
