<?php

namespace App\Http\Controllers;

use App\Models\ApprovalLayer;
use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    protected $category;

    public function __construct()
    {
        $this->category = 'Goals';
    }

    function index() {
        $parentLink = __('Report');
        $link = __('Report');

        $user = Auth::user()->employee_id;

        $locations = Location::select('company_name', 'area', 'work_area')->orderBy('area')->get();
        $groupCompanies = Location::select('company_name')
        ->orderBy('company_name')
        ->distinct()
        ->pluck('company_name');
        $companies = Company::select('contribution_level', 'contribution_level_code')->orderBy('contribution_level_code')->get();

        $selectYear = ApprovalRequest::select(DB::raw('YEAR(created_at) as year'))
        ->where('category', $this->category)
        ->distinct()
        ->orderBy('year')
        ->get();
        
        return view('reports.app', compact('locations', 'companies', 'groupCompanies', 'selectYear'),  [
            'parentLink' => $parentLink, 'link' => $link
        ]);
    }

    public function changesGroupCompany(Request $request)
    {
        $selectedGroupCompany = $request->input('groupCompany');

        // Initialize query to fetch locations
        $locationsQuery = Location::query();

        // Check if a specific group company is selected
        if ($selectedGroupCompany) {
            // Filter locations by the selected group company
            $locationsQuery->whereIn('company_name', $selectedGroupCompany);
        }

        // Fetch locations based on the modified query
        $locations = $locationsQuery->get();

        // Return JSON response with locations
        return response()->json([
            'locations' => $locations,
        ]);
    }
    
    public function getReportContent(Request $request)
    {
        // Get the authenticated user's employee_id
        $user = Auth::user();

        $filterYear = $request->input('filterYear') ? $request->input('filterYear') : now()->year;

        $employeeId = $user->employee_id;
        $report_type = $request->report_type;
        $group_company = $request->input('group_company');
        $location = $request->input('location');
        $company = $request->input('company');

        $filters = compact('report_type', 'group_company', 'location', 'company');

        // Start building the query
        $query = ApprovalRequest::with(['employee', 'manager', 'goal', 'initiated'])->where('category', $this->category);

        // Apply filters based on request parameters
        if ($request->filled('group_company')) {
            $query->whereHas('employee', function ($query) use ($group_company) {
                $query->whereIn('group_company', $group_company);
            });
        }
        if ($request->filled('location')) {
            $query->whereHas('employee', function ($query) use ($location) {
                $query->whereIn('work_area_code', $location);
            });
        }

        if ($request->filled('company')) {
            $query->whereHas('employee', function ($query) use ($company) {
                $query->whereIn('contribution_level_code', $company);
            });
        }

        $query->whereYear('created_at', $filterYear);

        $query->whereHas('approvalLayer', function ($query) use ($employeeId) {
            $query->where('employee_id', $employeeId)
                  ->orWhere('approver_id', $employeeId);
        });
        
        // Fetch the data based on the constructed query
        $data = $query->get();

        $data->map(function($item) {
            // Format created_at
            $createdDate = Carbon::parse($item->created_at);

                $item->formatted_created_at = $createdDate->format('d M Y');

            // Format updated_at
            $updatedDate = Carbon::parse($item->updated_at);

                $item->formatted_updated_at = $updatedDate->format('d M Y');

            // Determine name and approval layer
            if ($item->sendback_to == $item->employee->employee_id) {
                $item->name = $item->employee->fullname . ' (' . $item->employee->employee_id . ')';
                $item->approvalLayer = '';
            } else {
                $item->name = $item->manager->fullname . ' (' . $item->manager->employee_id . ')';
                $item->approvalLayer = ApprovalLayer::where('employee_id', $item->employee_id)
                                                    ->where('approver_id', $item->current_approval_id)
                                                    ->value('layer');
            }

            return $item;
        });

        // Determine the report type and return the appropriate view
        if ($report_type === 'Goal') {
            return view('reports.goal', compact('data'))->render();
        } else {
            return view('reports.empty')->render();
        }
    }

}
