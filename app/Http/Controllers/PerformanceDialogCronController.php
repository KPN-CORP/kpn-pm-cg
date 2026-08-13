<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

use App\Models\PerformanceDialog;
use App\Mail\PerformanceDialogDraftReminderMail;
use App\Mail\PerformanceDialogOverdueScheduleReminderMail;
use App\Mail\PerformanceDialogUpcomingScheduleReminderMail;

class PerformanceDialogCronController extends Controller
{
    public function __construct() {}

    public function upcomingScheduleReminder()
    {
        $tomorrowStart = now()->addDay()->startOfDay();
        $tomorrowEnd = now()->addDay()->endOfDay();

        $dialogs = PerformanceDialog::with([
            'employee',
            'employeeManager',
        ])
        ->where('status', 'Scheduled')
        ->whereBetween('start_date', [$tomorrowStart, $tomorrowEnd])
        ->whereNull('deleted_at')
        ->get();

        foreach ($dialogs as $dialog) {
            if (!empty($dialog->employee?->email)) {
                Mail::to($dialog->employee->email)
                    ->bcc('dali.kewara@kpn-corp.com')
                    ->queue(new PerformanceDialogUpcomingScheduleReminderMail([
                        "employee_manager_name" => $dialog->employeeManager?->fullname ?? "-",
                        "employee_name" => $dialog->employee?->fullname ?? "-",
                        "employee_designation" => $dialog->employee?->designation ?? "-",
                        "formatted_start_date" => Carbon::parse($dialog->start_date)->format('d F Y'),
                        "formatted_start_time" => Carbon::parse($dialog->start_date)->format('H:i'),
                        "url" => "",
                        "is_manager" => false,
                    ]));
            }

            if (!empty($dialog->employeeManager?->email)) {
                Mail::to($dialog->employeeManager->email)
                    ->bcc('dali.kewara@kpn-corp.com')
                    ->queue(new PerformanceDialogUpcomingScheduleReminderMail([
                        "employee_manager_name" => $dialog->employeeManager?->fullname ?? "-",
                        "employee_name" => $dialog->employee?->fullname ?? "-",
                        "employee_designation" => $dialog->employee?->designation ?? "-",
                        "formatted_start_date" => Carbon::parse($dialog->start_date)->format('d F Y'),
                        "formatted_start_time" => Carbon::parse($dialog->start_date)->format('H:i'),
                        "url" => "",
                        "is_manager" => true,
                    ]));
            }
        }
    }

    public function overdueScheduleReminder()
    {
        $dialogs = PerformanceDialog::with([
                'employee',
                'employeeManager',
            ])
            ->where('status', 'Scheduled')
            ->where('start_date', '<', now())
            ->whereNull('deleted_at')
            ->get();

        foreach ($dialogs as $dialog) {
            if (empty($dialog->employeeManager?->email)) {
                continue;
            }

            Mail::to($dialog->employeeManager->email)
                ->bcc('dali.kewara@kpn-corp.com')
                ->queue(new PerformanceDialogOverdueScheduleReminderMail([
                    'employee_manager_name'  => $dialog->employeeManager?->fullname ?? "-",
                    'employee_name' => $dialog->employee?->fullname ?? "-",
                    'url'           => "",
                ]));
        }
    }

    public function draftReminder()
    {
        $dialogs = PerformanceDialog::with([
                'employee',
                'employeeManager',
            ])
            ->where('status', 'Draft')
            ->whereNull('deleted_at')
            ->get();

        foreach ($dialogs as $dialog) {
            if (empty($dialog->employeeManager?->email)) {
                continue;
            }

            Mail::to($dialog->employeeManager->email)
                ->bcc('dali.kewara@kpn-corp.com')
                ->queue(new PerformanceDialogDraftReminderMail([
                    'employee_manager_name'  => $dialog->employeeManager?->fullname ?? "-",
                    'employee_name' => $dialog->employee?->fullname ?? "-",
                    'url'           => "",
                ]));
        }
    }
}
