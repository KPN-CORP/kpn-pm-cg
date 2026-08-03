<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Barryvdh\DomPDF\Facade\Pdf as PDF;

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

        $performanceDialogs = PerformanceDialog::with(['employee', 'employeeManager'])
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
            $isActionAcknowledge = false;

            if ($scheduleAt != "-" && Carbon::parse($scheduleAt)->lt($now) && $status == "Scheduled") {
                $status = "Overdue";
            }

            if (($status == "Done" || $status == "Submitted") && (!$row->acknowledge_date || empty($row->acknowledge_date))) {
                $isActionAcknowledge = true;
            }
            $isActionAcknowledge = true;

            if (($status == "Done" || $status == "Submitted") && $row->acknowledge_date && !empty($row->acknowledge_date)) {
                $isActionDownload = true;
            }

            $formattedScheduleAt = $scheduleAt != "-" ? Carbon::parse($scheduleAt)->format('Y-m-d H:i:s') : '-';
            $formattedInitiatedAt = $initiatedAt != "-" ? Carbon::parse($initiatedAt)->format('Y-m-d H:i:s') : '-';

            $rows[] = [
                "id" => $row->id,
                "employee_id" => $row->employee_id,
                "employee_name" => $row->employee?->fullname ?? "-",
                "employee_manager_name" => $row->employeeManager?->fullname ?? "-",
                "formatted_schedule_at" => $formattedScheduleAt,
                "formatted_initiated_at" => $formattedInitiatedAt,
                "status" => $status,
                "is_action_download" => $isActionDownload,
                "is_action_acknowledge" => $isActionAcknowledge,
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

            $loggedInEmployee = $this->loggedInUser?->employee;
            if (!$loggedInEmployee) {
                Session::flash('error', [
                    'title' => 'Cannot create or edit performance dialog',
                    'message' => "Employee not found"
                ]);

                return redirect()->back();
            }

            $loggedInEmployeeID = $loggedInEmployee->employee_id;

            $employeeID = $loggedInEmployeeID;
            $employeeName = $loggedInEmployee->fullname;
            $employeeJobLevel = $loggedInEmployee->job_level;
            $employeeGroupCompany = $loggedInEmployee->group_company;
            $employeeUnit = $loggedInEmployee->unit;
            $employeeDesignationName = $loggedInEmployee->designation_name;

            $performanceDialogTypes = [];
            $performanceDialogOthersTypeName = "";
            $performanceDialogStartDate = "";
            $performanceDialogDueDate = "";
            $performanceDialogSummary = "";
            $performanceDialogDevelopmentPlan = "";
            $performanceDialogAdditionalNotes = "";

            $isShowEmployeeDetail = false;
            $isShowSelectEmployee = true;
            $isShowStartDate = true;
            $isFormApproval = false;
            $isFormView = false;
            $isFormEdit = false;
            $isFormCreate = true;
            $isFormAcknowledge = false;
            $isFormDelete = false;
            $isPerformanceDialogTypesReadonly = false;
            $isOthersPerformanceDialogTypeReadonly = false;
            $isPerformanceDialogStartDateReadonly = false;
            $isPerformanceDialogDueDateReadonly = false;
            $isPerformanceDialogSummaryReadonly = false;
            $isPerformanceDialogDevelopmentPlanReadonly = false;
            $isPerformanceDialogAdditionalNotesReadonly = false;

            $redirectBack = "";

            if (!empty($request->id)) {
                $id = $request->id;
            }

            $directApprovalLayer = ApprovalLayer::with(['employeeManager'])->where('employee_id', $loggedInEmployee)->where('layer', 1)->first();
            $employeeManagerID = $directApprovalLayer?->employeeManager?->employee_id ?? null;

            $performanceDialog = PerformanceDialog::with(['employee', 'employeeManager'])
                ->where('id', $id)
                ->where('deleted_at', null)
                ->first();

            if ($performanceDialog) {
                $isFormCreate = false;

                if (!$performanceDialog->employee) {
                    Session::flash('error', [
                        'title' => 'Performance dialog error',
                        'message' => "Employee data not found!"
                    ]);

                    return redirect()->back();
                }

                if (!$performanceDialog->employeeManager) {
                    Session::flash('error', [
                        'title' => 'Performance dialog error',
                        'message' => "Employee manager data not found!"
                    ]);

                    return redirect()->back();
                }

                $period = $performanceDialog->period;

                $employee = $performanceDialog->employee;
                $employeeID = $employee->employee_id;
                $employeeName = $employee->fullname;
                $employeeJobLevel = $employee->job_level;
                $employeeGroupCompany = $employee->group_company;
                $employeeUnit = $employee->unit;
                $employeeDesignationName = $employee->designation_name;

                $employeeManager = $performanceDialog->employeeManager;
                $employeeManagerID = $employeeManager->employee_id;

                $performanceDialogTypes = $performanceDialog->type_datas;
                $performanceDialogOthersTypeName = $performanceDialog->others_type_name;
                $performanceDialogStartDate = $performanceDialog->start_date;
                $performanceDialogDueDate = $performanceDialog->due_date;
                $performanceDialogSummary = $performanceDialog->summary;
                $performanceDialogDevelopmentPlan = $performanceDialog->development_plan;
                $performanceDialogAdditionalNotes = $performanceDialog->additional_notes;

                if ($loggedInEmployeeID == $employeeManagerID) {
                    $redirectBack = route('performance-dialog-task');

                    if ($request->action == "edit") {
                        $isFormEdit = true;
                    } else if ($request->action == "delete") {
                        $isFormDelete = true;
                    } else {
                        $isFormView = true;
                    }
                } else if ($loggedInEmployeeID == $employeeID) {
                    $redirectBack = route('performance-dialog.my-history');

                    if ($request->action == "acknowledge") {
                        $isFormAcknowledge = true;
                    } else {
                        $isFormView = true;
                    }
                } else {
                    $isFormView = true;
                }

                if ($isFormApproval) {
                    $isPerformanceDialogTypesReadonly = true;
                    $isOthersPerformanceDialogTypeReadonly = true;
                    $isPerformanceDialogDueDateReadonly = true;
                    $isPerformanceDialogStartDateReadonly = true;
                } else if ($isFormView || $isFormAcknowledge || $isFormDelete) {
                    $isPerformanceDialogTypesReadonly = true;
                    $isOthersPerformanceDialogTypeReadonly = true;
                    $isPerformanceDialogDueDateReadonly = true;
                    $isPerformanceDialogSummaryReadonly = true;
                    $isPerformanceDialogDevelopmentPlanReadonly = true;
                    $isPerformanceDialogAdditionalNotesReadonly = true;
                    $isPerformanceDialogStartDateReadonly = true;
                }

                $isShowEmployeeDetail = true;
                $isShowSelectEmployee = false;
            }

            $masterPerformanceDialogTypes = PerformanceDialogType::where("is_active", true)->where("deleted_at", null)->get();

            $reportees = ApprovalLayer::with(["employee"])->where("approver_id", $loggedInEmployeeID)->get();

            return view('pages.performance-dialog.form', [
                "parentLink" => "Performance Dialog",
                "link" => "Form",
                "id" => $id,
                "period" => $period,
                "employee_id" => $employeeID,
                "employee_name" => $employeeName,
                "employee_job_level" => $employeeJobLevel,
                "employee_group_company" => $employeeGroupCompany,
                "employee_unit" => $employeeUnit,
                "employee_designation_name" => $employeeDesignationName,
                "employee_manager_id" => $employeeManagerID,
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
                "is_form_create" => $isFormCreate,
                "is_form_edit" => $isFormEdit,
                "is_form_acknowledge" => $isFormAcknowledge,
                "is_form_delete" => $isFormDelete,
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
            $loggedInUserID = $loggedInUser->id;
            $loggedInEmployeeID = $loggedInUser->employee_id;

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
            $startDate = $request->performance_dialog_start_date;
            $dueDate = $request->performance_dialog_due_date;
            $summary = $request->performance_dialog_summary;
            $developmentPlan = $request->performance_dialog_development_plan;
            $additionalNotes = $request->performance_dialog_additional_notes;
            $actionDraft = $request->has('action_draft');
            $actionSubmit = $request->has('action_submit');

            if (!$actionDraft && !$actionSubmit) {
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
            }

            if (empty($period)) {
                $period = now()->year;
            }

            $employee = Employee::where("employee_id", $loggedInEmployeeID)
                ->where("id", $loggedInUserID)
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
                ->where('approver_id', $loggedInEmployeeID)
                ->get();
            if (!$reporteeEmployees || $reporteeEmployees->count() < 1) {
                return response()->json([
                    'status' => false,
                    'message' => "You don't have any reportees!",
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
                    'message' => "There is no employee to process",
                    'errors' => []
                ], 422);
            }

            $performanceDialog = null;

            if (!empty($id)) {
                $performanceDialog = PerformanceDialog::where('id', $id)
                    ->where('deleted_at', null)
                    ->first();
            }

            if ($performanceDialog) {
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
                    'start_date' => $startDate,
                    'initiate_date' => $initiateDate,
                    'due_date' => $dueDate,
                    'type_ids' => json_encode($typeIDs),
                    'others_type_name' => $othersType,
                    'status' => $status,
                    'updated_by' => $loggedInUserID,
                    'updated_at' => Carbon::now(),
                ]);
            } else {
                $insertData = [];

                foreach ($processEmployees as $row) {
                    if ($row->approver_id != $loggedInEmployeeID) {
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
                        'start_date' => $startDate,
                        'due_date' => $dueDate,
                        'type_ids' => json_encode($typeIDs),
                        'others_type_name' => $othersType,
                        'status' => $status,
                        'created_by' => $loggedInUserID,
                        'created_at' => Carbon::now(),
                        'updated_by' => $loggedInUserID,
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
            $loggedInUserID = $loggedInUser->id;
            $loggedInUserEmployeeID = $loggedInUser->employee_id;
            $redirect = route('performance-dialog.my-history');

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

            $performanceDialog = PerformanceDialog::where("id", $id)->first();
            if (!$performanceDialog) {
                return response()->json([
                    'status' => false,
                    'message' => "Performance dialog not found!",
                    'errors' => []
                ], 422);
            }
            if ($performanceDialog->employee_id != $loggedInUserEmployeeID) {
                return response()->json([
                    'status' => false,
                    'message' => "Performance dialog doesn't match with the current employee!",
                    'errors' => []
                ], 422);
            }

            $performanceDialog->update([
                'acknowledge_by' => $loggedInUserID,
                'acknowledge_date' => Carbon::now(),
                'updated_by' => $loggedInUserID,
                'updated_at' => Carbon::now(),
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

    public function download($id) {
        try {
            $loggedInUser = $this->loggedInUser;
            $loggedInUserID = $loggedInUser->id;
            $loggedInUserEmployeeID = $loggedInUser->employee_id;

            $loggedInEmployee = Employee::where("employee_id", $loggedInUserEmployeeID)
                ->where("id", $loggedInUserID)
                ->first();
            if (!$loggedInEmployee) {
                Session::flash('error', [
                    'title' => 'Error',
                    'message' => "Employee not found"
                ]);

                return redirect()->back();
            }

            $performanceDialog = PerformanceDialog::with(['employee', 'employeeManager'])
            ->where("id", $id)
            ->first();
            if (!$performanceDialog) {
                Session::flash('error', [
                    'title' => 'Error',
                    'message' => "Performance dialog not found"
                ]);

                return redirect()->back();
            }

            $employeeUnit = $performanceDialog->employee?->unit ?? "-";
            $employeeName = $performanceDialog->employee?->fullname ?? "-";
            $employeeID = $performanceDialog->employee?->employee_id ?? "-";
            $employeeDesignation = $performanceDialog->employee?->designation ?? "-";
            $employeeManagerName = $performanceDialog->employeeManager?->fullname ?? "-";
            $employeeManagerID = $performanceDialog->employeeManager?->employee_id ?? "-";
            $employeeManagerDesignation = $performanceDialog->employeeManager?->designation ?? "-";
            $dialogTypes = $performanceDialog->type_datas;
            $summary = $performanceDialog->summary ?? "-";
            $developmentPlan = $performanceDialog->development_plan ?? "-";
            $additionalNotes = $performanceDialog->additional_notes ?? "-";
            $othersTypeName = $performanceDialog->others_type_name ?? "-";
            $formattedDiscussionDate = $performanceDialog->start_date != "-" ? Carbon::parse($performanceDialog->start_date)->format('Y-m-d H:i:s') : '-';
            $formattedDueDate = $performanceDialog->due_date != "-" ? Carbon::parse($performanceDialog->due_date)->format('Y-m-d H:i:s') : '-';
            $formattedInitiateDate = $performanceDialog->initiate_date != "-" ? Carbon::parse($performanceDialog->initiate_date)->format('Y-m-d H:i:s') : '-';
            $formattedAcknowledgeDate = $performanceDialog->acknowledge_date != "-" ? Carbon::parse($performanceDialog->acknowledge_date)->format('Y-m-d H:i:s') : '-';

            $masterDialogTypes = PerformanceDialogType::where("is_active", true)->where("deleted_at", null)->get();

            $pdf = PDF::loadView(
                "pages.performance-dialog.form-pdf",
                [
                    "formatted_discussion_date" => $formattedDiscussionDate,
                    "employee_unit" => $employeeUnit,
                    "employee_name" => $employeeName,
                    "employee_id" => $employeeID,
                    "employee_designation" => $employeeDesignation,
                    "employee_manager_name" => $employeeManagerName,
                    "employee_manager_id" => $employeeManagerID,
                    "employee_manager_designation" => $employeeManagerDesignation,
                    "dialog_types" => $dialogTypes,
                    "master_dialog_types" => $masterDialogTypes,
                    "summary" => $summary,
                    "development_plan" => $developmentPlan,
                    "formatted_due_date" => $formattedDueDate,
                    "additional_notes" => $additionalNotes,
                    "formatted_initiate_date" => $formattedInitiateDate,
                    "formatted_acknowledge_date" => $formattedAcknowledgeDate,
                    "others_type_name" => $othersTypeName
                ],
            )
                ->setPaper("a4", "portrait")
                ->set_option("enable_php", true);

            return $pdf->stream("Performance Dialog - " . $employeeID . " - " . $formattedDiscussionDate . ".pdf");
        } catch (Exception $e) {
            Session::flash('error', [
                'title' => 'Error',
                'message' => "General error: " . $e->getMessage()
            ]);

            return redirect()->back();
        }
    }
}
