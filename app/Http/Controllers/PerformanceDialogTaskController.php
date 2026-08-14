<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

use App\Models\PerformanceDialog;
use App\Models\ApprovalLayer;
use App\Models\Employee;
use App\Services\AppService;
use App\Imports\PerformanceDialogManagerImport;
use App\Exports\InvalidPerformanceDialogManagerImport;
use App\Mail\PerformanceDialogNewScheduleNotifMail;

class PerformanceDialogTaskController extends Controller
{
    protected $loggedInUser;
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->loggedInUser = Auth::user();
        $this->appService = $appService;
    }

    public function index(Request $request) {
        $loggedInUserID = $this->loggedInUser->id;
        $loggedInEmployeeID = $this->loggedInUser->employee_id;
        $period = now()->year;
        $currentActiveStatus = "All";
        $currentFilterInitiateDate = "";

        if (!empty($request->period)) {
            $period = $request->period;
        }

        if (!empty($request->filterYear)) {
            $period = $request->filterYear;
        }

        if (!empty($request->filterStatus)) {
            $currentActiveStatus = $request->filterStatus;
        }

        if (!empty($request->filterInitiateDate)) {
            $currentFilterInitiateDate = $request->filterInitiateDate;
        }

        $rows = [];
        $totalTeam = 0;
        $totalDone = 0;
        $totalScheduled = 0;
        $totalDraft = 0;
        $totalOverdue = 0;
        $totalNotScheduled = 0;

        if ($currentActiveStatus == 'Not Scheduled') {
            $performanceDialogs = collect();
        } else {
            $performanceDialogs = PerformanceDialog::with(['employee'])
                ->where('manager_employee_id', $loggedInEmployeeID)
                ->where('period', $period)
                ->whereNull('deleted_at');

            if (in_array($currentActiveStatus, ['Draft', 'Scheduled', 'Done'])) {
                $performanceDialogs->where('status', $currentActiveStatus);
            } elseif ($currentActiveStatus == 'Overdue') {
                $performanceDialogs
                    ->where('status', 'Scheduled')
                    ->where('start_date', '<', now());
            }

            if (!empty($currentFilterInitiateDate)) {
                $performanceDialogs->whereDate('initiate_date', $currentFilterInitiateDate);
            }

            $performanceDialogs = $performanceDialogs->get();
        }

        $now = Carbon::now();

        foreach($performanceDialogs as $row) {
            $dueDate = $row->due_date ?? "-";
            $scheduleAt = $row->start_date ?? "-";
            $initiatedAt = $row->initiate_date ?? "-";
            $status = $row->status ?? "-";
            $isActionEdit = false;
            $isActionEditInitiate = false;
            $isActionDownload = false;
            $isActionDelete = false;

            if ($scheduleAt != "-" && Carbon::parse($scheduleAt)->lt($now) && $status == "Scheduled") {
                $status = "Overdue";
            }

            if ($status == "Scheduled" || $status == "Overdue") {
                $isActionEditInitiate = true;
            }

            if ($status == "Draft" || $status == "Submitted") {
                $isActionEdit = true;
            }

            if ($status == "Done" || $status == "Submitted") {
                $isActionDownload = true;
            }

            if ($status == "Scheduled" || $status == "Draft") {
                $isActionDelete = true;
            }

            if ($status == "Done") {
                $totalDone += 1;
            }

            if ($status == "Scheduled") {
                $totalScheduled += 1;
            }

            if ($status == "Draft") {
                $totalDraft += 1;
            }

            if ($status == "Overdue") {
                $totalOverdue += 1;
            }

            $formattedScheduleAt = $scheduleAt != "-" ? Carbon::parse($scheduleAt)->format('Y-m-d H:i:s') : '-';
            $formattedInitiatedAt = $initiatedAt != "-" ? Carbon::parse($initiatedAt)->format('Y-m-d H:i:s') : '-';

            $rows[] = [
                "id" => $row->id,
                "employee_id" => $row->employee_id,
                "employee_name" => $row->employee?->fullname ?? "-",
                "formatted_schedule_at" => $formattedScheduleAt,
                "formatted_initiated_at" => $formattedInitiatedAt,
                "status" => $status,
                "is_action_initiate" => false,
                "is_action_schedule" => false,
                "is_action_edit" => $isActionEdit,
                "is_action_edit_initiate" => $isActionEditInitiate,
                "is_action_download" => $isActionDownload,
                "is_action_delete" => $isActionDelete
            ];
        }

        $performanceDialogGroupByEmployeeID = $performanceDialogs->groupBy('employee_id');

        $performanceDialogYears = PerformanceDialog::select('period')
            ->where('manager_employee_id', $loggedInEmployeeID)
            ->distinct()
            ->orderBy('period')
            ->pluck('period');

        if ($performanceDialogYears->isEmpty()) {
            $performanceDialogYears = collect([$period]);
        }

        $performanceDialogStatuses = [
            "All",
            "Draft",
            "Not Scheduled",
            "Scheduled",
            "Done",
            "Overdue"
        ];

        $reportees = ApprovalLayer::with(["employee"])
            ->where("approver_id", $loggedInEmployeeID)
            ->get();
        $totalTeam = $reportees->count();

        foreach($reportees as $reportee) {
            $reporteePerformanceDialog = $performanceDialogGroupByEmployeeID[$reportee->employee_id] ?? null;

            if ($reporteePerformanceDialog || in_array($currentActiveStatus, ['Draft', 'Scheduled', 'Done', 'Overdue']) || !empty($currentFilterInitiateDate)) {
                continue;
            }

            $totalNotScheduled += 1;

            $rows[] = [
                "id" => null,
                "employee_id" => $reportee->employee_id,
                "employee_name" => $reportee->employee?->fullname ?? "-",
                "formatted_schedule_at" => "-",
                "formatted_initiated_at" => "-",
                "status" => "Not Scheduled",
                "is_action_initiate" => false,
                "is_action_schedule" => true,
                "is_action_edit" => false,
                "is_action_edit_initiate" => false,
                "is_action_download" => false,
                "is_action_delete" => false
            ];
        }

        return view('pages.performance-dialog.task-box', [
            "parentLink" => "Performance Dialog",
            "link" => "Task Box",
            "period" => $period,
            "user_id" => $loggedInUserID,
            "employee_id" => $loggedInEmployeeID,
            "performance_dialog_years" => $performanceDialogYears,
            "current_active_status" => $currentActiveStatus,
            "performance_dialog_statuses" => $performanceDialogStatuses,
            "current_filter_initiate_date" => $currentFilterInitiateDate,
            "rows" => $rows,
            "reportees" => $reportees,
            "total_team" => $totalTeam,
            "total_done" => $totalDone,
            "total_scheduled" => $totalScheduled,
            "total_draft" => $totalDraft,
            "total_overdue" => $totalOverdue,
            "total_not_scheduled" => $totalNotScheduled
        ]);
    }

    public function import(Request $request)
    {
        $userID = $this->loggedInUser->id;

        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store($path='public/uploads');
            Log::info("File uploaded successfully: " . $filePath);
        } else {
            Log::error("File upload failed.");
            return back()->with('error', "File upload failed.");
        }

        DB::enableQueryLog();

        // log

        try {
            $import = new PerformanceDialogManagerImport($filePath, $userID);

            Excel::import($import, $filePath);

            $import->saveToDatabase();
            $import->saveTransaction();

            $invalidEmployees = $import->getInvalidEmployees();

            $message = 'Data imported successfully.';

            if (!empty($invalidEmployees)) {
                session()->put('invalid_employees', $invalidEmployees);

                $message = 'Some of import data failed! <a href="' . route('performance-dialog-task.invalid-export') . '"><u>Click here to download the list of errors.</u></a>';

                return redirect()->back()->with('error', $message)->with('error_client', 'Some of import data failed!');
            }

            $queries = DB::getQueryLog();

            Log::info($userID ." Executed queries import performance dialog manager: ", $queries);
            Log::info($userID ." Performance Dialog import : Data imported successfully.");

            return redirect()->back()->with('success', $message);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->errors()[0][0]);
        } catch (\Exception $e) {
            $errorMessage = "Import failed: " . $e->getMessage();

            Log::error($userID . " " . $errorMessage);

            return back()->with('error', $errorMessage);
        }
    }

    public function invalidExport()
    {
        $invalidEmployees = session('invalid_employees');

        if (empty($invalidEmployees)) {
            return redirect()->back()->with('success', 'No invalid employees to export.');
        }

        return Excel::download(new InvalidPerformanceDialogManagerImport($invalidEmployees), 'errors_performance_dialog_import.xlsx');
    }

    public function setSchedule(Request $request) {
        try {
            $loggedInUser = $this->loggedInUser;
            $userID = $loggedInUser->id;
            $managerEmployeeID = $loggedInUser->employee?->employee_id ?? null;
            $period = now()->year;
            $employeeIDs = [];
            $startDate = Carbon::parse($request->start_date);
            $status = "Scheduled";
            $redirect = route('performance-dialog-task');

            if ($startDate && !empty($startDate)) {
                $startDateYear = $startDate->year;

                if ($startDateYear) {
                    $period = $startDateYear;
                }
            }

            if (!empty($request->employee_id)) {
                $employeeIDs[] = $request->employee_id;
            } else if (!empty($request->employee_ids)) {
                foreach ($request->employee_ids as $row) {
                    $employeeIDs[] = $row;
                }
            }

            if (empty($employeeIDs)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Missing employee ID',
                    'data' => []
                ], 422);
            }

            $employeeManager = Employee::where("employee_id", $managerEmployeeID)
                ->where("id", $userID)
                ->first();
            if (!$employeeManager) {
                return response()->json([
                    'status' => false,
                    'message' => "Employee manager not found",
                    'errors' => []
                ], 422);
            }

            $reportees = ApprovalLayer::with(['employee', 'employeeManager'])
                ->where("approver_id", $managerEmployeeID)
                ->whereIn('employee_id', $employeeIDs)
                ->get();
            if (!$reportees || empty($reportees)) {
                return response()->json([
                    'status' => false,
                    'message' => "Employee not found",
                    'errors' => []
                ], 422);
            }

            $insertData = [];

            foreach ($reportees as $row) {
                if ($row->approver_id != $managerEmployeeID) {
                    continue;
                }

                $insertData[] = [
                    "manager_employee_id" => $managerEmployeeID,
                    "employee_id" => $row->employee_id,
                    "period" => $period,
                    "start_date" => $startDate,
                    "status" => $status,
                    "created_by" => $userID,
                    "created_at" => Carbon::now(),
                    "updated_by" => $userID,
                    "updated_at" => Carbon::now(),
                ];
            }

            PerformanceDialog::insert($insertData);

            foreach ($reportees as $row) {
                if ($row->approver_id != $managerEmployeeID) {
                    continue;
                }

                if (!empty($row->employee?->email)) {
                    Mail::to($row->employee->email)
                        ->bcc('dali.kewara@kpn-corp.com')
                        ->queue(new PerformanceDialogNewScheduleNotifMail([
                            "employee_manager_name" => $row->employeeManager?->fullname ?? "-",
                            "employee_name" => $row->employee?->fullname ?? "-",
                            "employee_designation" => $row->employee?->designation ?? "-",
                            "formatted_start_date" => Carbon::parse($startDate)->format('d F Y'),
                            "formatted_start_time" => Carbon::parse($startDate)->format('H:i'),
                            "url" => "",
                            "is_manager" => false,
                        ]));
                }

                if (!empty($row->employeeManager?->email)) {
                    Mail::to($row->employeeManager->email)
                        ->bcc('dali.kewara@kpn-corp.com')
                        ->queue(new PerformanceDialogNewScheduleNotifMail([
                            "employee_manager_name" => $row->employeeManager?->fullname ?? "-",
                            "employee_name" => $row->employee?->fullname ?? "-",
                            "employee_designation" => $row->employee?->designation ?? "-",
                            "formatted_start_date" => Carbon::parse($startDate)->format('d F Y'),
                            "formatted_start_time" => Carbon::parse($startDate)->format('H:i'),
                            "url" => "",
                            "is_manager" => true,
                        ]));
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Success',
                'redirect' => $redirect,
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'errors' => []
            ], 500);
        }
    }

    public function delete(Request $request) {
        try {
            $loggedInUser = $this->loggedInUser;
            $loggedInUserID = $loggedInUser->id;
            $loggedInUserEmployeeID = $loggedInUser->employee_id;
            $redirect = route('performance-dialog-task');

            $id = $request->id;

            $loggedInEmployee = Employee::where("employee_id", $loggedInUserEmployeeID)
                ->where("id", $loggedInUserID)
                ->first();
            if (!$loggedInEmployee) {
                return response()->json([
                    'status' => false,
                    'message' => "Employee not found!",
                    'errors' => []
                ], 422);
            }

            $performanceDialog = PerformanceDialog::where("id", $id)
                ->where("manager_employee_id", $loggedInUserEmployeeID)
                ->first();
            if (!$performanceDialog) {
                return response()->json([
                    'status' => false,
                    'message' => "Performance dialog not found!",
                    'errors' => []
                ], 422);
            }

            $performanceDialog->update([
                'deleted_by' => $loggedInUserID,
                'deleted_at' => Carbon::now(),
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Success',
                'redirect' => $redirect,
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'errors' => []
            ], 500);
        }
    }
}
