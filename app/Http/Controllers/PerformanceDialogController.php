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

        $performanceDialogs = PerformanceDialog::with(['employee', 'employeeCreatedBy', 'employeeUpdatedBy'])
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

        foreach($performanceDialogs as $row) {
            $row->formatted_start_date = $row->start_date ? Carbon::parse($row->start_date)->format('d M Y') : '-';
            $row->formatted_end_date = $row->end_date ? Carbon::parse($row->end_date)->format('d M Y') : '-';
            $row->formatted_due_date = $row->due_date ? Carbon::parse($row->due_date)->format('d M Y') : '-';
            $row->formatted_created_at = $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-';
            $row->formatted_updated_at = $row->updated_at ? Carbon::parse($row->updated_at)->format('d M Y') : '-';
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

            $isFormApproval = false;
            $isFormView = false;
            $isPerformanceReviewTypesReadonly = false;
            $isOthersPerformanceReviewTypeReadonly = false;
            $isPerformanceReviewStartDateReadonly = false;
            $isPerformanceReviewEndDateReadonly = false;
            $isPerformanceReviewDueDateReadonly = false;
            $isPerformanceReviewSummaryReadonly = false;
            $isPerformanceReviewDevelopmentPlanReadonly = false;
            $isPerformanceReviewAdditionalNotesReadonly = false;

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

                $PerformanceDialogTypes = $performanceDialog->type_datas;
                $PerformanceDialogOthersTypeName = $performanceDialog->others_type_name;
                $PerformanceDialogStartDate = $performanceDialog->start_date;
                $PerformanceDialogEndDate = $performanceDialog->end_date;
                $PerformanceDialogDueDate = $performanceDialog->due_date;
                $PerformanceDialogSummary = $performanceDialog->summary;
                $PerformanceDialogDevelopmentPlan = $performanceDialog->development_plan;
                $PerformanceDialogAdditionalNotes = $performanceDialog->additional_notes;
                $formattedPerformanceDialogStartDate = Carbon::parse($performanceDialog->start_date)->format('Y-m-d');
                $formattedPerformanceDialogEndDate = Carbon::parse($performanceDialog->end_date)->format('Y-m-d');
                $formattedPerformanceDialogDueDate = Carbon::parse($performanceDialog->due_date)->format('Y-m-d');

                if ($loggedInEmployee->employee_id == $managerEmployeeID) {
                    $redirectBack = route('performance-dialog-task');

                    if ($performanceDialog->status == "Pending") {
                        $isFormApproval = true;
                    } else {
                        $isFormView = true;
                    }
                } else if ($loggedInEmployee->employee_id == $employeeID) {
                    $redirectBack = route('performance-dialog.my-history');

                    if ($performanceDialog->status != "Draft") {
                        $isFormView = true;
                    }
                }

                if ($isFormApproval) {
                    $isPerformanceReviewTypesReadonly = true;
                    $isOthersPerformanceReviewTypeReadonly = true;
                    $isPerformanceReviewStartDateReadonly = true;
                    $isPerformanceReviewEndDateReadonly = true;
                    $isPerformanceReviewDueDateReadonly = true;
                } else if ($isFormView) {
                    $isPerformanceReviewTypesReadonly = true;
                    $isOthersPerformanceReviewTypeReadonly = true;
                    $isPerformanceReviewStartDateReadonly = true;
                    $isPerformanceReviewEndDateReadonly = true;
                    $isPerformanceReviewDueDateReadonly = true;
                    $isPerformanceReviewSummaryReadonly = true;
                    $isPerformanceReviewDevelopmentPlanReadonly = true;
                    $isPerformanceReviewAdditionalNotesReadonly = true;
                }
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
                "is_form_approval" => $isFormApproval,
                "is_form_view" => $isFormView,
                "is_performance_review_types_readonly" => $isPerformanceReviewTypesReadonly,
                "is_others_performance_review_type_readonly" => $isOthersPerformanceReviewTypeReadonly,
                "is_performance_review_start_date_readonly" => $isPerformanceReviewStartDateReadonly,
                "is_performance_review_end_date_readonly" => $isPerformanceReviewEndDateReadonly,
                "is_performance_review_due_date_readonly" => $isPerformanceReviewDueDateReadonly,
                "is_performance_review_summary_readonly" => $isPerformanceReviewSummaryReadonly,
                "is_performance_review_development_plan_readonly" => $isPerformanceReviewDevelopmentPlanReadonly,
                "is_performance_review_additional_notes_readonly" => $isPerformanceReviewAdditionalNotesReadonly,
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
            $actionApprove = $request->has('action_approve');

            if (!$actionDraft && !$actionSubmit && !$actionApprove) {
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
                $status = "Pending";
            } else if ($actionApprove) {
                $status = "Approved";
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
                ]);
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
                    $performanceDialog->update([
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
                }
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

            $redirect = route('performance-dialog.my-history');

            if ($actionApprove) {
                $redirect = route('performance-dialog-task');
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
