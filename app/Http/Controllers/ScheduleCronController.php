<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use App\Jobs\SendReminderScheduleEmailJob;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScheduleCronController extends Controller
{
    function reminderDailySchedules()
    {
        $today = date("Y-m-d");
        $dayOfWeek = now()->format("D");

        $schedules = DB::table("schedules")
            ->where("start_date", "<=", $today)
            ->where("end_date", ">=", $today)
            ->where("checkbox_reminder", "=", 1)
            ->whereNull("deleted_at")
            ->whereIn("event_type", ["schedulepa", "goals"])
            ->get();

        foreach ($schedules as $schedule) {
            if ($schedule->checkbox_reminder == "1") {
                $sendReminder = false;

                if ($schedule->inputState == "beforeenddate") {
                    $reminderStartDate = Carbon::parse(
                        $schedule->end_date,
                    )->subDays($schedule->before_end_date);
                    if (
                        Carbon::today()->between(
                            $reminderStartDate,
                            Carbon::parse($schedule->end_date),
                        )
                    ) {
                        $sendReminder = true;
                    }
                } elseif ($schedule->inputState == "repeaton") {
                    $repeatDays = explode(",", $schedule->repeat_days);
                    if (in_array($dayOfWeek, $repeatDays)) {
                        $sendReminder = true;
                    }
                }

                if ($sendReminder) {
                    if ($schedule->event_type == "goals") {
                        $query = Employee::query();
                        $query->doesntHave("goal");
                    } elseif ($schedule->event_type == "schedulepa") {
                        $query = EmployeeAppraisal::query();
                        $query->where(function ($q) {
                            // Kondisi pertama: karyawan yang tidak memiliki penilaian
                            $q->doesntHave("appraisalpa");

                            // Kondisi kedua: karyawan yang sudah memiliki penilaian tetapi statusnya masih draft
                            $q->orWhereHas("appraisalpa", function ($q2) {
                                $q2->where("form_status", "draft");
                            });
                        });
                    }

                    if ($schedule->employee_type) {
                        $query->whereIn(
                            "employee_type",
                            explode(",", $schedule->employee_type),
                        );
                    }

                    if ($schedule->bisnis_unit) {
                        $query->whereIn(
                            "group_company",
                            explode(",", $schedule->bisnis_unit),
                        );
                    }

                    if ($schedule->company_filter) {
                        $query->whereIn(
                            "contribution_level_code",
                            explode(",", $schedule->company_filter),
                        );
                    }

                    if ($schedule->location_filter) {
                        $query->whereIn(
                            "work_area_code",
                            explode(",", $schedule->location_filter),
                        );
                    }

                    $query->where(
                        "date_of_joining",
                        "<=",
                        $schedule->last_join_date,
                    );

                    $query->whereNotIn("job_level", ["9B", "10A", "10B"]);

                    $employees = $query->get();
                    // dd($employees);

                    foreach ($employees as $employee) {
                        $email = $employee->email;
                        // $email = "eriton.dewa@kpn-corp.com";
                        $name = $employee->fullname;
                        $message = $schedule->messages;

                        dispatch(
                            new SendReminderScheduleEmailJob(
                                $email,
                                $name,
                                $message,
                            ),
                        );
                        //echo "penerima : $email <br>nama : $name <br>isi email : $message <br>";
                    }
                }
            }
        }
    }
}
