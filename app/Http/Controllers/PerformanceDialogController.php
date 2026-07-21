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

        $performanceDialogs = PerformanceDialog::with(['createdByEmployee', 'updatedByEmployee'])
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

        return view('pages.performance-dialog.my-performance-dialog', [
            "parentLink" => "Performance Dialog",
            "link" => "Performance Dialogs",
            "period" => $period,
            "user_id" => $userID,
            "employee_id" => $employeeID,
            "performance_dialogs" => $performanceDialogs,
            "performance_dialog_years" => $performanceDialogYears,
        ]);
    }

    public function form(Request $request) {
        try {
            $ = null;
            $employee_id = $this->loggedInUser;
            $employee_id = $employee_id->employee_id;
            $employeeName = $employee_id->fullname;
            $employeeJobLevel = $employee_id->job_level;
            $employeeGroupCompany = $employee_id->group_company;
            $employeeUnit = $employee_id->unit;
            $employeeDesignationName = $employee_id->designation_name;

            $managerEmployeeID = "";

            $PerformanceDialogTypes = [];
            $PerformanceDialogOthersTypeName = "";
            $PerformanceDialogDueDate = "";
            $formattedPerformanceDialogDueDate = "";
            $PerformanceDialogSummary = "";
            $PerformanceDialogDevelopmentPlan = "";
            $PerformanceDialogAdditionalNotes = "";

            if ($request->id) {
                $ = $request->id;
            }

            $approvalLayers = ApprovalLayer::with(['employee'])->where('employee_id', $employee_id)->where('layer', 1)->get();
            if (!$datas->first()) {
                Session::flash('error', [
                    'title' => 'Cannot create goal',
                    'message' => "There is no direct manager assigned in your position!"
                ]);

                if ($this->user != $) {
                    return redirect('team-goals');
                }
                return redirect('goals');
            }

            $PerformanceDialog = PerformanceDialog::where('id', $)
                ->where('deleted_at', null)
                ->first();

            if ($PerformanceDialog) {
                $managerEmployeeID = $PerformanceDialog->manager_employee_id;

                $PerformanceDialogTypes = $PerformanceDialog->type_datas;
                $PerformanceDialogOthersTypeName = $PerformanceDialog->others_type_name;
                $PerformanceDialogDueDate = $PerformanceDialog->due_date;
                $PerformanceDialogSummary = $PerformanceDialog->summary;
                $PerformanceDialogDevelopmentPlan = $PerformanceDialog->development_plan;
                $PerformanceDialogAdditionalNotes = $PerformanceDialog->additional_notes;
                $formattedPerformanceDialogDueDate = Carbon::parse($PerformanceDialogDueDate)->format('Y-m-d');
            }

            $masterPerformanceDialogTypes = PerformanceDialogType::where("is_active", true)->where("deleted_at", null)->get();

            return view('pages.performance-dialog.form', [
                "parentLink" => "Appraisal",
                "link" => "Performance Review",
                "period" => $,
                "employee_id" => $,
                "employee_name" => $employeeName,
                "employee_job_level" => $employeeJobLevel,
                "employee_group_company" => $employeeGroupCompany,
                "employee_unit" => $employeeUnit,
                "employee_designation_name" => $employeeDesignationName,
                "manager_employee_id" => $,
                "master_performance_review_types" => $masterPerformanceDialogTypes,
                "performance_review_types" => $PerformanceDialogTypes,
                "performance_review_others_type_name" => $PerformanceDialogOthersTypeName,
                "performance_review_due_date" => $PerformanceDialogDueDate,
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
            $loggedInUserID = $this->loggedInUser->id;
            $contributorID = $request->contributor_id;
            $period = $this->appService->appraisalPeriod();
            $id = $request->id;
            $employeeID = $request->employee_id;
            $typeIDs = $request->performance_review_types;
            $othersType = $request->others_performance_review_type;
            $dueDate = $request->performance_review_due_date;
            $summary = $request->performance_review_summary;
            $developmentPlan = $request->performance_review_development_plan;
            $additionalNotes = $request->performance_review_additional_notes;
            $actionDraft = $request->has('action_draft');
            $actionSubmit = $request->has('action_submit');

            if (!empty($request->period)) {
                $period = $request->period;
            }

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

            $appraisalContributor = AppraisalContributor::where('id', $contributorID)->where('period', $period)->first();
            if (empty($appraisalContributor)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Appraisal contributor not found',
                    'errors' => []
                ]);
            }

            $appraisal = Appraisal::with(['employee'])->where('id', $appraisalContributor->appraisal_id)->where('period', $period)->first();
            if (empty($appraisal)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Appraisal not found',
                    'errors' => []
                ]);
            }
            if (empty($appraisal->employee)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Employee not found',
                    'errors' => []
                ]);
            }

            $employee = $appraisal->employee;

            if ($employeeID != $employee->employee_id) {
                return response()->json([
                    'status' => false,
                    'message' => "Employee doesn't match",
                    'errors' => []
                ]);
            }

            if (!empty($id)) {
                $PerformanceDialog = PerformanceDialog::where('id', $id)
                    ->where('deleted_at', null)
                    ->first();
            } else {
                $PerformanceDialog = PerformanceDialog::where('manager_employee_id', $contributorID)
                    ->where('employee_id', $employeeID)
                    ->where('period', $period)
                    ->where('deleted_at', null)
                    ->first();
            }

            if ($PerformanceDialog) {
                $PerformanceDialog->update([
                    'summary' => $summary,
                    'development_plan' => $developmentPlan,
                    'additional_notes' => $additionalNotes,
                    'due_date' => $dueDate,
                    'type_ids' => $typeIDs,
                    'others_type_name' => $othersType,
                    'status' => $status,
                    'updated_by' => $loggedInUserID,
                    'updated_at' => Carbon::now(),
                ]);
            } else {
                PerformanceDialog::create([
                    'manager_employee_id' => $contributorID,
                    'employee_id' => $employeeID,
                    'period' => $period,
                    'summary' => $summary,
                    'development_plan' => $developmentPlan,
                    'additional_notes' => $additionalNotes,
                    'due_date' => $dueDate,
                    'type_ids' => $typeIDs,
                    'others_type_name' => $othersType,
                    'status' => $status,
                    'created_by' => $loggedInUserID,
                    'created_at' => Carbon::now(),
                    'updated_by' => $loggedInUserID,
                    'updated_at' => Carbon::now(),
                ]);
            }

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
