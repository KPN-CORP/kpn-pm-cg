<?php

namespace App\Http\Controllers;

use Exception;
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

            $performanceReviewTypes = PerformanceReviewType::where("is_active", true)->pluck("name");

            return view('pages.performance-review.form-add', [
                "parentLink" => "Appraisal",
                "link" => "Performance Review",
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

    public function formEdit($id) {
        try {
            return view('pages.performance-review.form-edit', [
                "parentLink" => "Appraisal",
                "link" => "Performance Review",
            ]);
        } catch (Exception $e) {
            return view('pages.performance-review.form-edit', [
                'errors' => []
            ]);
        }
    }

    public function create(Request $request) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => []
            ]);
        }
    }

    public function update(Request $request) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => []
            ]);
        }
    }

    public function delete(Request $request) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Success',
                'data' => []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error',
                'errors' => []
            ]);
        }
    }
}
