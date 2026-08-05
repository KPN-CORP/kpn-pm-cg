<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;

use App\Models\PerformanceDialog;

class PerformanceDialogCronController extends Controller
{
    public function __construct() {}

    public function upcomingScheduleReminder () {}

    public function overdueScheduleReminder () {}

    public function draftScheduleReminder () {}
}
