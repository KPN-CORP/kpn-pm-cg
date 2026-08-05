<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\PerformanceDialogCronController;

class PerformanceDialogDraftScheduleReminder extends Command
{
    protected $signature = "app:performanceDialog:draftSchedule";
    protected $description = "Performance Dialog Draft Schedule";

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $controller = new PerformanceDialogCronController();
        $controller->draftScheduleReminder();
        $this->info("Email reminder sent successfully.");
    }
}
