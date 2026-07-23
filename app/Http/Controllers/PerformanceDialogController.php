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
        $period = $this->appService->appraisalPeriod();

        if (!empty($request->period)) {
            $period = $request->period;
        }

        $performanceDialogs = PerformanceDialog::with(['employeeCreatedBy', 'employeeUpdatedBy'])
            ->where('employee_id', $employeeID)
            ->where('period', $period)
            ->where('deleted_at', null)
            ->get();

        $performanceDialogYears = PerformanceDialog::select('period')
            ->where('employee_id', $employeeID)
            ->distinct()
            ->orderBy('period')
            ->pluck('period');

        if (empty($performanceDialogYears)) {
            $performanceDialogYears = [$period];
        }

        return view('pages.performance-dialog.my-history', [
            "parentLink" => "Performance Dialog",
            "link" => "My History",
            "period" => $period,
            "user_id" => $userID,
            "employee_id" => $employeeID,
            "performance_dialogs" => $performanceDialogs,
            "performance_dialog_years" => $performanceDialogYears,
        ]);
    }

    public function taskBox(Request $request) {
        $userID = $this->loggedInUser->id;
        $employeeID = $this->loggedInUser->employee_id;
        $period = $this->appService->appraisalPeriod();

        if (!empty($request->period)) {
            $period = $request->period;
        }

        $performanceDialogs = PerformanceDialog::with(['employeeCreatedBy', 'employeeUpdatedBy'])
            ->where('manager_employee_id', $employeeID)
            ->where('period', $period)
            ->where('deleted_at', null)
            ->get();

        $performanceDialogYears = PerformanceDialog::select('period')
            ->where('manager_employee_id', $employeeID)
            ->distinct()
            ->orderBy('period')
            ->pluck('period');

        if (empty($performanceDialogYears)) {
            $performanceDialogYears = [$period];
        }

        return view('pages.performance-dialog.task-box', [
            "parentLink" => "Performance Dialog",
            "link" => "Task Box",
            "period" => $period,
            "user_id" => $userID,
            "employee_id" => $employeeID,
            "performance_dialogs" => $performanceDialogs,
            "performance_dialog_years" => $performanceDialogYears,
        ]);
    }

    public function form(Request $request) {
        try {
            $id = null;
            $period = now()->year;

            $employee = $this->loggedInUser->employee;
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

            $PerformanceDialogTypes = [];
            $PerformanceDialogOthersTypeName = "";
            $PerformanceDialogStartDate = "";
            $PerformanceDialogEndDate = "";
            $PerformanceDialogDueDate = "";
            $formattedPerformanceDialogStartDate = "";
            $formattedPerformanceDialogEndDate = "";
            $formattedPerformanceDialogDueDate = "";
            $PerformanceDialogSummary = "";
            $PerformanceDialogDevelopmentPlan = "";
            $PerformanceDialogAdditionalNotes = "";

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

            $PerformanceDialog = PerformanceDialog::with(['employee'])
                ->where('id', $id)
                ->where('deleted_at', null)
                ->first();

            if ($PerformanceDialog) {
                if (!$PerformanceDialog->employee) {
                    Session::flash('error', [
                        'title' => 'Cannot create or edit performance dialog',
                        'message' => "Employee data nor found!"
                    ]);

                    return redirect()->back();
                }

                $period = $PerformanceDialog->period;

                $employee = $PerformanceDialog->employee;
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

                $managerEmployeeID = $PerformanceDialog->manager_employee_id;

                $PerformanceDialogTypes = $PerformanceDialog->type_datas;
                $PerformanceDialogOthersTypeName = $PerformanceDialog->others_type_name;
                $PerformanceDialogStartDate = $PerformanceDialog->start_date;
                $PerformanceDialogEndDate = $PerformanceDialog->end_date;
                $PerformanceDialogDueDate = $PerformanceDialog->due_date;
                $PerformanceDialogSummary = $PerformanceDialog->summary;
                $PerformanceDialogDevelopmentPlan = $PerformanceDialog->development_plan;
                $PerformanceDialogAdditionalNotes = $PerformanceDialog->additional_notes;
                $formattedPerformanceDialogStartDate = Carbon::parse($PerformanceDialogStartDate)->format('Y-m-d');
                $formattedPerformanceDialogEndDate = Carbon::parse($PerformanceDialogEndDate)->format('Y-m-d');
                $formattedPerformanceDialogDueDate = Carbon::parse($PerformanceDialogDueDate)->format('Y-m-d');
            }

            $masterPerformanceDialogTypes = PerformanceDialogType::where("is_active", true)->where("deleted_at", null)->get();

            return view('pages.performance-dialog.form', [
                "parentLink" => "Performance Dialog",
                "link" => "Form",
                "period" => $period,
                "employee_id" => $employeeID,
                "employee_name" => $employeeName,
                "employee_job_level" => $employeeJobLevel,
                "employee_group_company" => $employeeGroupCompany,
                "employee_unit" => $employeeUnit,
                "employee_designation_name" => $employeeDesignationName,
                "manager_employee_id" => $managerEmployeeID,
                "master_performance_review_types" => $masterPerformanceDialogTypes,
                "performance_review_types" => $PerformanceDialogTypes,
                "performance_review_others_type_name" => $PerformanceDialogOthersTypeName,
                "performance_review_start_date" => $PerformanceDialogStartDate,
                "performance_review_end_date" => $PerformanceDialogEndDate,
                "performance_review_due_date" => $PerformanceDialogDueDate,
                "formatted_performance_review_start_date" => $formattedPerformanceDialogStartDate,
                "formatted_performance_review_end_date" => $formattedPerformanceDialogEndDate,
                "formatted_performance_review_due_date" => $formattedPerformanceDialogDueDate,
                "performance_review_summary" => $PerformanceDialogSummary,
                "performance_review_development_plan" => $PerformanceDialogDevelopmentPlan,
                "performance_review_additional_notes" => $PerformanceDialogAdditionalNotes,
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

            $id = $request->id;
            $period = $request->period;
            $employeeID = $request->employee_id;
            $managerEmployeeID = $request->manager_employee_id;
            $typeIDs = $request->performance_review_types;
            $othersType = $request->others_performance_review_type;
            $startDate = $request->performance_review_start_date;
            $endDate = $request->performance_review_end_date;
            $dueDate = $request->performance_review_due_date;
            $summary = $request->performance_review_summary;
            $developmentPlan = $request->performance_review_development_plan;
            $additionalNotes = $request->performance_review_additional_notes;
            $actionDraft = $request->has('action_draft');
            $actionSubmit = $request->has('action_submit');

            if (!$actionDraft && !$actionSubmit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid action',
                    'errors' => []
                ]);
            }

            $status = "Pending";

            if ($actionDraft) {
                $status = "Draft";
            } else if ($actionSubmit) {
                $status = "Submitted";
            }

            if (!empty($startDate)) {
                $startDateYear = Carbon::parse($startDate)->year;

                if ($startDateYear) {
                    $period = $startDateYear;
                }
            }

            $employee = Employee::where("employee_id", $employeeID)
                ->where("id", $userID)
                ->first();
            if (!$employee) {
                return response()->json([
                    'status' => false,
                    'message' => "Employee not found",
                    'errors' => []
                ]);
            }

            $employeeManager = Employee::where("employee_id", $managerEmployeeID)
                ->where("id", $userID)
                ->first();
            if (!$managerEmployeeID) {
                return response()->json([
                    'status' => false,
                    'message' => "Employee manager not found",
                    'errors' => []
                ]);
            }

            $directApprovalLayer = ApprovalLayer::with(['employee'])->where('employee_id', $employeeID)->where('layer', 1)->first();
            if (!$directApprovalLayer) {
                return response()->json([
                    'status' => false,
                    'message' => "There is no direct manager assigned in your position!",
                    'errors' => []
                ]);
            }

            $PerformanceDialog = null;

            if (!empty($id)) {
                $PerformanceDialog = PerformanceDialog::where('id', $id)
                    ->where('deleted_at', null)
                    ->first();
            }

            if ($PerformanceDialog) {
                $PerformanceDialog->update([
                    'summary' => $summary,
                    'development_plan' => $developmentPlan,
                    'additional_notes' => $additionalNotes,
                    'start_date' => $startDate,
                    'period' => $period,
                    'end_date' => $endDate,
                    'due_date' => $dueDate,
                    'type_ids' => $typeIDs,
                    'others_type_name' => $othersType,
                    'status' => $status,
                    'updated_by' => $userID,
                    'updated_at' => Carbon::now(),
                ]);
            } else {
                PerformanceDialog::create([
                    'manager_employee_id' => $managerEmployeeID,
                    'employee_id' => $employeeID,
                    'period' => $period,
                    'summary' => $summary,
                    'development_plan' => $developmentPlan,
                    'additional_notes' => $additionalNotes,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'due_date' => $dueDate,
                    'type_ids' => $typeIDs,
                    'others_type_name' => $othersType,
                    'status' => $status,
                    'created_by' => $userID,
                    'created_at' => Carbon::now(),
                    'updated_by' => $userID,
                    'updated_at' => Carbon::now(),
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Success',
                'redirect' => route('performance-dialog.my-history'),
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'errors' => []
            ]);
        }
    }

    public function delete(Request $request) {
        try {
            return response()->json([
                'status' => true,
                'message' => 'Success',
                'redirect' => route('appraisals-task'),
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'errors' => []
            ]);
        }
    }
}
