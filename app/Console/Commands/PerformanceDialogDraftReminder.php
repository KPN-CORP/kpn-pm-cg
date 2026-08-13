<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\PerformanceDialogCronController;

class PerformanceDialogDraftReminder extends Command
{
    protected $signature = "app:performanceDialog:draftReminder";
    protected $description = "Performance Dialog Draft Reminder";

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $controller = new PerformanceDialogCronController();
        $controller->draftReminder();
        $this->info("Email reminder sent successfully.");
    }
}
