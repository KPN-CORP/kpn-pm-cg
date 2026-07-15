<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewType;
use App\Models\Appraisal;
use App\Models\AppraisalContributor;
use App\Services\AppService;

class PerformanceReviewController extends Controller
{
    protected $loggedInUser;
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->loggedInUser = Auth::user();
        $this->appService = $appService;
    }

    public function form(Request $request) {
        try {
            $contributorID = decrypt($request->contributor_id);
            $period = $this->appService->appraisalPeriod();

            if (!empty($request->period)) {
                $period = $request->period;
            }

            $appraisalContributor = AppraisalContributor::where('id', $contributorID)->where('period', $period)->first();
            if (empty($appraisalContributor)) {
                Session::flash('error', "Appraisal contributor not found");
                return redirect()->back();
            }

            $appraisal = Appraisal::with(['employee'])->where('id', $appraisalContributor->appraisal_id)->where('period', $period)->first();
            if (empty($appraisal)) {
                Session::flash('error', "Appraisal not found");
                return redirect()->back();
            }
            if (empty($appraisal->employee)) {
                Session::flash('error', "Employee not found");
                return redirect()->back();
            }

            $employee = $appraisal->employee;
            $employeeID = $employee->employee_id;
            $employeeName = $employee->fullname;
            $employeeJobLevel = $employee->job_level;
            $employeeGroupCompany = $employee->group_company;
            $employeeUnit = $employee->unit;
            $employeeDesignationName = $employee->designation_name;

            $performanceReviewTypes = [];
            $performanceReviewOthersTypeName = "";
            $performanceReviewDueDate = "";
            $formattedPerformanceReviewDueDate = "";
            $performanceReviewSummary = "";
            $performanceReviewDevelopmentPlan = "";
            $performanceReviewAdditionalNotes = "";

            $performanceReview = PerformanceReview::where('manager_employee_id', $contributorID)
                ->where('employee_id', $employeeID)
                ->where('period', $period)
                ->where('deleted_at', null)
                ->first();

            if ($performanceReview) {
                $performanceReviewTypes = $performanceReview->type_datas;
                $performanceReviewOthersTypeName = $performanceReview->others_type_name;
                $performanceReviewDueDate = $performanceReview->due_date;
                $performanceReviewSummary = $performanceReview->summary;
                $performanceReviewDevelopmentPlan = $performanceReview->development_plan;
                $performanceReviewAdditionalNotes = $performanceReview->additional_notes;
                $formattedPerformanceReviewDueDate = Carbon::parse($performanceReviewDueDate)->format('Y-m-d');
            }

            $masterPerformanceReviewTypes = PerformanceReviewType::where("is_active", true)->where("deleted_at", null)->get();

            return view('pages.performance-review.form', [
                "parentLink" => "Appraisal",
                "link" => "Performance Review",
                "period" => $period,
                "contributor_id" => $contributorID,
                "employee_id" => $employeeID,
                "employee_name" => $employeeName,
                "employee_job_level" => $employeeJobLevel,
                "employee_group_company" => $employeeGroupCompany,
                "employee_unit" => $employeeUnit,
                "employee_designation_name" => $employeeDesignationName,
                "master_performance_review_types" => $masterPerformanceReviewTypes,
                "performance_review_types" => $performanceReviewTypes,
                "performance_review_others_type_name" => $performanceReviewOthersTypeName,
                "performance_review_due_date" => $performanceReviewDueDate,
                "formatted_performance_review_due_date" => $formattedPerformanceReviewDueDate,
                "performance_review_summary" => $performanceReviewSummary,
                "performance_review_development_plan" => $performanceReviewDevelopmentPlan,
                "performance_review_additional_notes" => $performanceReviewAdditionalNotes,
            ]);
        } catch (Exception $e) {
            Session::flash('error', "General error: " . $e->getMessage());
            return redirect()->back();
        }
    }

    public function formAdd(Request $request) {
        try {
            $contributorID = decrypt($request->contributor_id);
            $period = $this->appService->appraisalPeriod();

            if (!empty($request->period)) {
                $period = $request->period;
            }

            $appraisalContributor = AppraisalContributor::where('id', $contributorID)->where('period', $period)->first();
            if (empty($appraisalContributor)) {
                Session::flash('error', "Appraisal contributor not found");
                return redirect()->back();
            }

            $appraisal = Appraisal::with(['employee'])->where('id', $appraisalContributor->appraisal_id)->where('period', $period)->first();
            if (empty($appraisal)) {
                Session::flash('error', "Appraisal not found");
                return redirect()->back();
            }
            if (empty($appraisal->employee)) {
                Session::flash('error', "Employee not found");
                return redirect()->back();
            }

            $employee = $appraisal->employee;
            $employeeID = $employee->employee_id;
            $employeeName = $employee->fullname;
            $employeeJobLevel = $employee->job_level;
            $employeeGroupCompany = $employee->group_company;
            $employeeUnit = $employee->unit;
            $employeeDesignationName = $employee->designation_name;

            $performanceReviewTypes = PerformanceReviewType::where("is_active", true);

            return view('pages.performance-review.form-add', [
                "parentLink" => "Appraisal",
                "link" => "Performance Review",
                "period" => $period,
                "contributor_id" => $contributorID,
                "employee_id" => $employeeID,
                "employee_name" => $employeeName,
                "employee_job_level" => $employeeJobLevel,
                "employee_group_company" => $employeeGroupCompany,
                "employee_unit" => $employeeUnit,
                "employee_designation_name" => $employeeDesignationName,
                "performance_review_types" => $performanceReviewTypes,
            ]);
        } catch (Exception $e) {
            Session::flash('error', "General error: " . $e->getMessage());
            return redirect()->back();
        }
    }

    public function formEdit(Request $request) {
        try {
            $contributorID = decrypt($request->contributor_id);
            $period = $this->appService->appraisalPeriod();

            if (!empty($request->period)) {
                $period = $request->period;
            }

            $appraisalContributor = AppraisalContributor::where('id', $contributorID)->where('period', $period)->first();
            if (empty($appraisalContributor)) {
                Session::flash('error', "Appraisal contributor not found");
                return redirect()->back();
            }

            $appraisal = Appraisal::with(['employee'])->where('id', $appraisalContributor->appraisal_id)->where('period', $period)->first();
            if (empty($appraisal)) {
                Session::flash('error', "Appraisal not found");
                return redirect()->back();
            }
            if (empty($appraisal->employee)) {
                Session::flash('error', "Employee not found");
                return redirect()->back();
            }

            $employee = $appraisal->employee;
            $employeeID = $employee->employee_id;
            $employeeName = $employee->fullname;
            $employeeJobLevel = $employee->job_level;
            $employeeGroupCompany = $employee->group_company;
            $employeeUnit = $employee->unit;
            $employeeDesignationName = $employee->designation_name;

            $performanceReviewTypes = PerformanceReviewType::where("is_active", true);

            return view('pages.performance-review.form-edit', [
                "parentLink" => "Appraisal",
                "link" => "Performance Review",
                "period" => $period,
                "contributor_id" => $contributorID,
                "employee_id" => $employeeID,
                "employee_name" => $employeeName,
                "employee_job_level" => $employeeJobLevel,
                "employee_group_company" => $employeeGroupCompany,
                "employee_unit" => $employeeUnit,
                "employee_designation_name" => $employeeDesignationName,
                "performance_review_types" => $performanceReviewTypes,
            ]);
        } catch (Exception $e) {
            Session::flash('error', "General error: " . $e->getMessage());
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
                $performanceReview = PerformanceReview::where('id', $id)
                    ->where('deleted_at', null)
                    ->first();
            } else {
                $performanceReview = PerformanceReview::where('manager_employee_id', $contributorID)
                    ->where('employee_id', $employeeID)
                    ->where('period', $period)
                    ->where('deleted_at', null)
                    ->first();
            }

            if ($performanceReview) {
                $performanceReview->update([
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
                PerformanceReview::create([
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
