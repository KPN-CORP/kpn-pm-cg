<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewType;
use App\Models\Appraisal;
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
            $contributorID = decrypt($request->appraisal_id);
            $period = $this->appService->appraisalPeriod();

            $appraisal = Appraisal::with(['employee', 'approvalRequest' => function($query) use ($period) {
                $query->where('category', 'Appraisal')->where('period', $period);
            }])->where('employee_id', $contributorID)->where('period', $period)->first();

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

            return view('pages.performance-review.form-add', [
                "employee_id" => $employeeID,
                "employee_name" => $employeeName,
                "employee_job_level" => $employeeJobLevel,
                "employee_group_company" => $employeeGroupCompany,
                "employee_unit" => $employeeUnit,
                "employee_designation_name" => $employeeDesignationName,
            ]);
        } catch (Exception $e) {
            Session::flash('error', "General error: " . $e->getMessage());
            return redirect()->back();
        }
    }

    public function formEdit($id) {
        try {
            return view('pages.performance-review.form-edit', [
                'data' => ""
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
