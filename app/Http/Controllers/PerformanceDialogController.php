<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

use App\Models\PerformanceDialog;
use App\Models\PerformanceDialogType;
use App\Models\ApprovalLayer;
use App\Models\Employee;

use App\Services\AppService;

class PerformanceDialogController extends Controller
{
    protected $loggedInUser;
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->loggedInUser = Auth::user();
        $this->appService = $appService;
    }

    public function index(Request $request) {
        $userID = $this->loggedInUser->id;
        $employeeID = $this->loggedInUser->employee_id;
        $period = now()->year;

        if (!empty($request->period)) {
            $period = $request->period;
        }

        if (!empty($request->filterYear)) {
            $period = $request->filterYear;
        }

        $rows = [];

        $performanceDialogs = PerformanceDialog::with(['employee'])
            ->where('employee_id', $employeeID)
            ->where('period', $period)
            ->where('deleted_at', null)
            ->get();

        $now = Carbon::now();

        foreach($performanceDialogs as $row) {
            $dueDate = $row->due_date ?? "-";
            $scheduleAt = $row->start_date ?? "-";
            $initiatedAt = $row->initiate_date ?? "-";
            $status = $row->status ?? "-";
            $isActionDownload = false;

            if ($scheduleAt != "-" && Carbon::parse($scheduleAt)->lt($now) && $status == "Scheduled") {
                $status = "Overdue";
            }

            if ($status == "Done" || $status == "Submitted") {
                $isActionDownload = true;
            }

            $formattedScheduleAt = $scheduleAt != "-" ? Carbon::parse($scheduleAt)->format('d M Y') : '-';
            $formattedInitiatedAt = $initiatedAt != "-" ? Carbon::parse($initiatedAt)->format('d M Y') : '-';

            $rows[] = [
                "id" => $row->id,
                "employee_id" => $row->employee_id,
                "employee_name" => $row->employee?->fullname ?? "-",
                "formatted_schedule_at" => $formattedScheduleAt,
                "formatted_initiated_at" => $formattedInitiatedAt,
                "status" => $status,
                "is_action_download" => $isActionDownload
            ];
        }

        $performanceDialogGroupByEmployeeID = $performanceDialogs->groupBy('employee_id');

        $performanceDialogYears = PerformanceDialog::select('period')
            ->where('employee_id', $employeeID)
            ->distinct()
            ->orderBy('period')
            ->pluck('period');

        if ($performanceDialogYears->isEmpty()) {
            $performanceDialogYears = collect([$period]);
        }

        return view('pages.performance-dialog.my-history', [
            "parentLink" => "Performance Dialog",
            "link" => "My History",
            "period" => $period,
            "user_id" => $userID,
            "employee_id" => $employeeID,
            "performance_dialog_years" => $performanceDialogYears,
            "rows" => $rows,
        ]);
    }

    public function form(Request $request) {
        try {
            $id = null;
            $period = now()->year;
            $loggedInEmployee = $this->loggedInUser->employee;

            $employee = $loggedInEmployee;
            if (!$employee) {
                Session::flash('error', [
                    'title' => 'Cannot create or edit performance dialog',
                    'message' => "Employee not found"
                ]);

                return redirect()->back();
            }

            $employeeID = $employee->employee_id;
            $employeeName = $employee->fullname;
            $employeeJobLevel = $employee->job_level;
            $employeeGroupCompany = $employee->group_company;
            $employeeUnit = $employee->unit;
            $employeeDesignationName = $employee->designation_name;

            $performanceDialogTypes = [];
            $performanceDialogOthersTypeName = "";
            $performanceDialogStartDate = "";
            $performanceDialogDueDate = "";
            $performanceDialogSummary = "";
            $performanceDialogDevelopmentPlan = "";
            $performanceDialogAdditionalNotes = "";

            $isShowEmployeeDetail = false;
            $isShowSelectEmployee = true;
            $isShowStartDate = false;
            $isFormApproval = false;
            $isFormView = false;
            $isPerformanceDialogTypesReadonly = false;
            $isOthersPerformanceDialogTypeReadonly = false;
            $isPerformanceDialogStartDateReadonly = true;
            $isPerformanceDialogDueDateReadonly = false;
            $isPerformanceDialogSummaryReadonly = false;
            $isPerformanceDialogDevelopmentPlanReadonly = false;
            $isPerformanceDialogAdditionalNotesReadonly = false;

            $redirectBack = "";

            if (!empty($request->id)) {
                $id = $request->id;
            }

            $directApprovalLayer = ApprovalLayer::with(['employeeManager'])->where('employee_id', $employeeID)->where('layer', 1)->first();
            if (!$directApprovalLayer) {
                Session::flash('error', [
                    'title' => 'Cannot create or edit performance dialog',
                    'message' => "There is no direct manager assigned in your position!"
                ]);

                return redirect()->back();
            }
            if (!$directApprovalLayer->employeeManager) {
                Session::flash('error', [
                    'title' => 'Cannot create or edit performance dialog',
                    'message' => "Manager not found!"
                ]);

                return redirect()->back();
            }

            $managerEmployeeID = $directApprovalLayer->approver_id;

            $performanceDialog = PerformanceDialog::with(['employee'])
                ->where('id', $id)
                ->where('deleted_at', null)
                ->first();

            if ($performanceDialog) {
                if (!$performanceDialog->employee) {
                    Session::flash('error', [
                        'title' => 'Cannot create or edit performance dialog',
                        'message' => "Employee data nor found!"
                    ]);

                    return redirect()->back();
                }

                $period = $performanceDialog->period;

                $employee = $performanceDialog->employee;
                if (!$employee) {
                    Session::flash('error', [
                        'title' => 'Cannot create or edit performance dialog',
                        'message' => "Employee not found"
                    ]);

                    return redirect()->back();
                }

                $employeeID = $employee->employee_id;
                $employeeName = $employee->fullname;
                $employeeJobLevel = $employee->job_level;
                $employeeGroupCompany = $employee->group_company;
                $employeeUnit = $employee->unit;
                $employeeDesignationName = $employee->designation_name;

                $managerEmployeeID = $performanceDialog->manager_employee_id;

                $performanceDialogTypes = $performanceDialog->type_datas;
                $performanceDialogOthersTypeName = $performanceDialog->others_type_name;
                $performanceDialogStartDate = $performanceDialog->start_date;
                $performanceDialogDueDate = $performanceDialog->due_date;
                $performanceDialogSummary = $performanceDialog->summary;
                $performanceDialogDevelopmentPlan = $performanceDialog->development_plan;
                $performanceDialogAdditionalNotes = $performanceDialog->additional_notes;

                if ($loggedInEmployee->employee_id == $managerEmployeeID) {
                    $redirectBack = route('performance-dialog-task');

                    if ($performanceDialog->status == "Pending") {
                        $isFormApproval = true;
                    } else if ($performanceDialog->status == "Done") {
                        $isFormView = true;
                    }
                } else if ($loggedInEmployee->employee_id == $employeeID) {
                    $redirectBack = route('performance-dialog.my-history');

                    // if ($performanceDialog->status != "Draft") {
                    //     $isFormView = true;
                    // }
                }

                if ($isFormApproval) {
                    $isPerformanceDialogTypesReadonly = true;
                    $isOthersPerformanceDialogTypeReadonly = true;
                    $isPerformanceDialogDueDateReadonly = true;
                } else if ($isFormView) {
                    $isPerformanceDialogTypesReadonly = true;
                    $isOthersPerformanceDialogTypeReadonly = true;
                    $isPerformanceDialogDueDateReadonly = true;
                    $isPerformanceDialogSummaryReadonly = true;
                    $isPerformanceDialogDevelopmentPlanReadonly = true;
                    $isPerformanceDialogAdditionalNotesReadonly = true;
                }

                $isShowEmployeeDetail = true;
                $isShowSelectEmployee = false;
                $isShowStartDate = true;
            }

            $masterPerformanceDialogTypes = PerformanceDialogType::where("is_active", true)->where("deleted_at", null)->get();

            $reportees = ApprovalLayer::with(["employee"])->where("approver_id", $employeeID)->get();

            return view('pages.performance-dialog.form', [
                "parentLink" => "Performance Dialog",
                "link" => "Form Performance Dialog",
                "id" => $id,
                "period" => $period,
                "employee_id" => $employeeID,
                "employee_name" => $employeeName,
                "employee_job_level" => $employeeJobLevel,
                "employee_group_company" => $employeeGroupCompany,
                "employee_unit" => $employeeUnit,
                "employee_designation_name" => $employeeDesignationName,
                "manager_employee_id" => $managerEmployeeID,
                "master_performance_dialog_types" => $masterPerformanceDialogTypes,
                "performance_dialog_types" => $performanceDialogTypes,
                "performance_dialog_others_type_name" => $performanceDialogOthersTypeName,
                "performance_dialog_start_date" => $performanceDialogStartDate,
                "performance_dialog_due_date" => $performanceDialogDueDate,
                "performance_dialog_summary" => $performanceDialogSummary,
                "performance_dialog_development_plan" => $performanceDialogDevelopmentPlan,
                "performance_dialog_additional_notes" => $performanceDialogAdditionalNotes,
                "is_show_employee_detail" => $isShowEmployeeDetail,
                "is_show_select_employee" => $isShowSelectEmployee,
                "is_show_start_date" => $isShowStartDate,
                "is_form_approval" => $isFormApproval,
                "is_form_view" => $isFormView,
                "is_performance_dialog_types_readonly" => $isPerformanceDialogTypesReadonly,
                "is_others_performance_dialog_type_readonly" => $isOthersPerformanceDialogTypeReadonly,
                "is_performance_dialog_start_date_readonly" => $isPerformanceDialogStartDateReadonly,
                "is_performance_dialog_due_date_readonly" => $isPerformanceDialogDueDateReadonly,
                "is_performance_dialog_summary_readonly" => $isPerformanceDialogSummaryReadonly,
                "is_performance_dialog_development_plan_readonly" => $isPerformanceDialogDevelopmentPlanReadonly,
                "is_performance_dialog_additional_notes_readonly" => $isPerformanceDialogAdditionalNotesReadonly,
                "reportees" => $reportees,
                "redirect_back" => $redirectBack,
            ]);
        } catch (Exception $e) {
            Session::flash('error', [
                'title' => 'Error',
                'message' => "General error: " . $e->getMessage()
            ]);

            return redirect()->back();
        }
    }

    public function createOrUpdate(Request $request) {
        try {
            $loggedInUser = $this->loggedInUser;
            $userID = $loggedInUser->id;
            $employeeID = $loggedInUser->employee_id;
            $employeeIDs = [];

            if ($request->performance_dialog_employee_ids && !empty($request->performance_dialog_employee_ids)) {
                $employeeIDs = $request->performance_dialog_employee_ids;
            } else if (!empty($request->employee_id)) {
                $employeeIDs[] = $request->employee_id;
            }

            $id = $request->id;
            $period = $request->period;
            $typeIDs = $request->performance_dialog_types;
            $othersType = $request->others_performance_dialog_type;
            $dueDate = $request->performance_dialog_due_date;
            $summary = $request->performance_dialog_summary;
            $developmentPlan = $request->performance_dialog_development_plan;
            $additionalNotes = $request->performance_dialog_additional_notes;
            $actionDraft = $request->has('action_draft');
            $actionSubmit = $request->has('action_submit');
            $actionApprove = $request->has('action_approve');

            if (!$actionDraft && !$actionSubmit && !$actionApprove) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid action',
                    'errors' => []
                ], 422);
            }

            $status = "Pending";

            if ($actionDraft) {
                $status = "Draft";
            } else if ($actionSubmit) {
                $status = "Done";
            } else if ($actionApprove) {
                $status = "Approved";
            }

            if (empty($period)) {
                $period = now()->year;
            }

            $employee = Employee::where("employee_id", $employeeID)
                ->where("id", $userID)
                ->first();
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => "Employee not found",
                    'errors' => []
                ], 422);
            }

            $reporteeEmployees = ApprovalLayer::with(['employee'])
                ->whereIn('employee_id', $employeeIDs)
                ->where('approver_id', $employeeID)
                ->get();
            if (!$reporteeEmployees || $reporteeEmployees->count() < 1) {
                return response()->json([
                    'status' => false,
                    'message' => "There is no direct manager assigned in your position!",
                    'errors' => []
                ], 422);
            }

            $reporteeEmployeeGroupByEmployeeID = $reporteeEmployees->keyBy('employee_id');

            $processEmployees = [];

            foreach ($employeeIDs as $row) {
                if (!isset($reporteeEmployeeGroupByEmployeeID[$row]) || !$reporteeEmployeeGroupByEmployeeID[$row] || !$reporteeEmployeeGroupByEmployeeID[$row]->employee) {
                    continue;
                }

                $processEmployees[] = $reporteeEmployeeGroupByEmployeeID[$row];
            }

            if (empty($processEmployees) || count($processEmployees) < 1) {
                return response()->json([
                    'status' => false,
                    'message' => "Employee not found",
                    'errors' => []
                ], 422);
            }

            $performanceDialog = null;

            if (!empty($id)) {
                $performanceDialog = PerformanceDialog::where('id', $id)
                    ->where('deleted_at', null)
                    ->first();
            }

            if ($actionApprove && !$performanceDialog) {
                return response()->json([
                    'status' => false,
                    'message' => "Performance dialog not found!",
                    'errors' => []
                ], 422);
            }

            if ($performanceDialog) {
                if ($actionApprove) {
                    $performanceDialog->update([
                        'summary' => $summary,
                        'development_plan' => $developmentPlan,
                        'additional_notes' => $additionalNotes,
                        'status' => $status,
                        'updated_by' => $userID,
                        'updated_at' => Carbon::now(),
                    ]);
                } else {
                    $initiateDate = Carbon::now();

                    if ($actionDraft) {
                        $initiateDate = null;
                    }

                    if ($performanceDialog->initiate_date && !empty($performanceDialog->initiate_date)) {
                        $initiateDate = $performanceDialog->initiate_date;
                    }

                    $performanceDialog->update([
                        'summary' => $summary,
                        'development_plan' => $developmentPlan,
                        'additional_notes' => $additionalNotes,
                        'period' => $period,
                        'initiate_date' => $initiateDate,
                        'due_date' => $dueDate,
                        'type_ids' => json_encode($typeIDs),
                        'others_type_name' => $othersType,
                        'status' => $status,
                        'updated_by' => $userID,
                        'updated_at' => Carbon::now(),
                    ]);
                }
            } else {
                $insertData = [];

                foreach ($processEmployees as $row) {
                    if ($row->approver_id != $employeeID) {
                        continue;
                    }

                    $insertData[] = [
                        'manager_employee_id' => $row->approver_id,
                        'employee_id' => $row->employee_id,
                        'period' => $period,
                        'summary' => $summary,
                        'development_plan' => $developmentPlan,
                        'additional_notes' => $additionalNotes,
                        'initiate_date' => Carbon::now(),
                        'start_date' => Carbon::now(),
                        'due_date' => $dueDate,
                        'type_ids' => json_encode($typeIDs),
                        'others_type_name' => $othersType,
                        'status' => $status,
                        'created_by' => $userID,
                        'created_at' => Carbon::now(),
                        'updated_by' => $userID,
                        'updated_at' => Carbon::now(),
                    ];
                }

                PerformanceDialog::insert($insertData);
            }

            $redirect = route('performance-dialog-task');

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

    public function acknowledge(Request $request) {
        try {
            $loggedInUser = $this->loggedInUser;
            $userID = $loggedInUser->id;
            $employeeID = $loggedInUser->employee_id;
            $redirect = route('performance-dialog.my-history');

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
