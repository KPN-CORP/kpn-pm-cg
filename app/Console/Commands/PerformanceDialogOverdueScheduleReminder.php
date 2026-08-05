<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\PerformanceDialogCronController;

class PerformanceDialogOverdueScheduleReminder extends Command
{
    protected $signature = "app:performanceDialog:overdueSchedule";
    protected $description = "Performance Dialog Overdue Schedule";

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $controller = new PerformanceDialogCronController();
        $controller->overdueScheduleReminder();
        $this->info("Email reminder sent successfully.");
    }
}
