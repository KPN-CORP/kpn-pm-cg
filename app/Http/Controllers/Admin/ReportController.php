<?php

namespace App\Http\Controllers\Admin;

use App\Exports\GoalExport;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\ApprovalLayer;
use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use App\Models\Goal;
use App\Models\Location;
use App\Models\Report;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    protected $groupCompanies;
    protected $companies;
    protected $locations;
    protected $permissionGroupCompanies;
    protected $permissionCompanies;
    protected $permissionLocations;
    protected $roles;
    protected $category;
    
    public function __construct()
    {
        $this->category = 'Goals';
        $this->roles = Auth::user()->roles;
        
        $restrictionData = [];
        if(!is_null($this->roles)){
            $restrictionData = json_decode($this->roles->first()->restriction, true);
        }
        
        $this->permissionGroupCompanies = $restrictionData['group_company'] ?? [];
        $this->permissionCompanies = $restrictionData['contribution_level_code'] ?? [];
        $this->permissionLocations = $restrictionData['work_area_code'] ?? [];

        $groupCompanyCodes = $restrictionData['group_company'] ?? [];

        $this->groupCompanies = Location::select('company_name')
            ->when(!empty($groupCompanyCodes), function ($query) use ($groupCompanyCodes) {
                return $query->whereIn('company_name', $groupCompanyCodes);
            })
            ->orderBy('company_name')->distinct()->pluck('company_name');

        $workAreaCodes = $restrictionData['work_area_code'] ?? [];

        $this->locations = Location::select('company_name', 'area', 'work_area')
            ->when(!empty($workAreaCodes) || !empty($groupCompanyCodes), function ($query) use ($workAreaCodes, $groupCompanyCodes) {
                return $query->where(function ($query) use ($workAreaCodes, $groupCompanyCodes) {
                    if (!empty($workAreaCodes)) {
                        $query->whereIn('work_area', $workAreaCodes);
                    }
                    if (!empty($groupCompanyCodes)) {
                        $query->orWhereIn('company_name', $groupCompanyCodes);
                    }
                });
            })
            ->orderBy('area')
            ->get();

        $companyCodes = $restrictionData['contribution_level_code'] ?? [];

        $this->companies = Company::select('contribution_level', 'contribution_level_code')
            ->when(!empty($companyCodes), function ($query) use ($companyCodes) {
                return $query->whereIn('contribution_level_code', $companyCodes);
            })
            ->orderBy('contribution_level_code')->get();
    }
    
    function index(Request $request) {
        $parentLink = 'Admin';
        $link = __('Report');

        $locations = Location::select('company_name', 'area', 'work_area')->orderBy('area')->get();
        $groupCompanies = Location::select('company_name')
        ->orderBy('company_name')
        ->distinct()
        ->pluck('company_name');
        $companies = Company::select('contribution_level', 'contribution_level_code')->orderBy('contribution_level_code')->get();

        $period = date('Y');

        $selectYear = Schedule::withTrashed()
        ->where('schedule_periode', '!=', $period)
        ->selectRaw('DISTINCT schedule_periode as period')
        ->orderBy('period', 'ASC')
        ->get();

        return view('reports-admin.app', compact('locations', 'companies', 'groupCompanies', 'link', 'parentLink', 'selectYear', 'period'));
    }

    public function changesGroupCompany(Request $request)
    {
        $selectedGroupCompany = $request->input('groupCompany');

        // Initialize query to fetch locations
        $locationsQuery = Location::query();

        // Check if a specific group company is selected
        if ($selectedGroupCompany) {
            // Filter locations by the selected group company
            $locationsQuery->where('company_name', $selectedGroupCompany);
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
        $user = Auth::user();
        $employeeId = $user->employee_id;
        $report_type = $request->report_type;
        $period = $request->input('filterYear');
        $group_company = $request->input('group_company', []);
        $location = $request->input('location', []);
        $company = $request->input('company', []);
        $permissionLocations = $this->permissionLocations;
        $permissionCompanies = $this->permissionCompanies;
        $permissionGroupCompanies = $this->permissionGroupCompanies;

        $filters = compact('period', 'report_type', 'group_company', 'location', 'company');

        // Start building the query
        if ($report_type === 'Goal') {
            $query = ApprovalRequest::with(['employee', 'manager', 'goal', 'initiated'])->where('category', $this->category)->whereHas('employee')->whereHas('manager')->whereHas('initiated');

            $criteria = [
                'work_area_code' => $permissionLocations,
                'group_company' => $permissionGroupCompanies,
                'contribution_level_code' => $permissionCompanies,
            ];

            $query->where(function ($query) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if ($value !== null && !empty($value)) {
                        $query->orWhereHas('employee', function ($subquery) use ($key, $value) {
                            $subquery->whereIn($key, $value);
                        });
                    }
                }
            });

            if (!empty($group_company)) {
                $query->whereHas('employee', function ($query) use ($group_company) {
                    $query->whereIn('group_company', $group_company)->orderBy('fullname');
                });
            }
            if (!empty($location)) {
                $query->whereHas('employee', function ($query) use ($location) {
                    $query->whereIn('work_area_code', $location);
                });
            }
            if (!empty($company)) {
                $query->whereHas('employee', function ($query) use ($company) {
                    $query->whereIn('contribution_level_code', $company);
                });
            }
            if (!empty($period)) {
                $query->where('period', $period);
            } else {
                $query->where('period', date('Y'));
            }

            // Apply employee filters
            $data = $query->get();

            $data->map(function($item) {
                // Format created_at
                $createdDate = Carbon::parse($item->created_at);

                    $item->formatted_created_at = $createdDate->format('d M Y g:ia');
    
                // Format updated_at
                $updatedDate = Carbon::parse($item->updated_at);

                    $item->formatted_updated_at = $updatedDate->format('d M Y g:ia');

                // Determine name and approval layer
                if ($item->sendback_to == $item->employee->employee_id) {
                    $item->name = $item->employee->fullname . ' (' . $item->employee->employee_id . ')';
                    $item->approvalLayer = '';
                } else {
                    $item->name = $item->manager ? $item->manager->fullname . ' (' . $item->manager->employee_id . ')' : '';
                    $item->approvalLayer = ApprovalLayer::where('employee_id', $item->employee_id)
                                                        ->where('approver_id', $item->current_approval_id)
                                                        ->value('layer');
                }

                return $item;
            });

            $route = 'reports-admin.goal';
        } elseif ($report_type === 'Employee') {
            $query = Employee::query()->orderBy('fullname')->whereNull('deleted_at'); // Start with Employee model

            if (!empty($group_company)) {
                    $query->whereIn('group_company', $group_company)->orderBy('fullname');
            }
            if (!empty($location)) {
                    $query->whereIn('work_area_code', $location);
            }
            if (!empty($company)) {
                    $query->whereIn('contribution_level_code', $company);
            }

            $criteria = [
                'work_area_code' => $permissionLocations,
                'group_company' => $permissionGroupCompanies,
                'contribution_level_code' => $permissionCompanies,
            ];
    
            $query->where(function ($query) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if ($value !== null && !empty($value)) {
                        $query->whereIn($key, $value);
                    }
                }
            });

            $data = $query->get();
            foreach ($data as $employee) {
                $employee->access_menu = json_decode($employee->access_menu, true);
            }
            $route = 'reports-admin.employee';
        // } elseif ($request->reportType === 'EmployeePA') {
        } elseif ($report_type === 'EmployeePA') {
            $query = EmployeeAppraisal::query()->orderBy('fullname'); // Start with Employee model

            if (!empty($group_company)) {
                    $query->whereIn('group_company', $group_company)->orderBy('fullname');
            }
            if (!empty($location)) {
                    $query->whereIn('work_area_code', $location);
            }
            if (!empty($company)) {
                    $query->whereIn('contribution_level_code', $company);
            }

            $criteria = [
                'work_area_code' => $permissionLocations,
                'group_company' => $permissionGroupCompanies,
                'contribution_level_code' => $permissionCompanies,
            ];
    
            $query->where(function ($query) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if ($value !== null && !empty($value)) {
                        $query->whereIn($key, $value);
                    }
                }
            });

            $designations = Designation::select('designation_name', 'job_code')
            ->orderBy('parent_company_id', 'asc')
            ->orderBy('designation_name', 'asc')
            ->orderBy('job_code', 'asc')
            ->groupBy('job_code', 'designation_name', 'parent_company_id')
            ->get();
            $departments = Department::select('department_name')
            ->orderBy('department_name', 'asc')
            ->groupBy('department_name')
            ->get();
            $companies = Company::orderBy('contribution_level_code', 'asc')->get();
            $locations = Location::orderBy('area', 'asc')->get();

            $jobLevel = EmployeeAppraisal::select('job_level')->distinct()->orderBy('job_level', 'asc')->get();

            $data = $query->get();
            foreach ($data as $employee) {
                $employee->access_menu = json_decode($employee->access_menu, true);
            }
            $route = 'reports-admin.employeepa';

            $link = __('Report');

            return view($route, compact('data', 'link', 'filters', 'designations','departments','companies','locations', 'jobLevel'));
        }else {
            $data = collect(); // Empty collection for unknown report types
            $route = 'reports-admin.empty';
        }

        $link = __('Report');

        return view($route, compact('data', 'link', 'filters'));
    }


    public function generateReportExcel(Request $request)
    {
        // Logika untuk generate report
        
        $reportType = $request->export_report_type;
        $groupCompany = $request->export_group_company;
        $company = $request->export_company;
        $location = $request->export_location;
        $period = $request->export_period;
        $permissionLocations = $this->permissionLocations;
        $permissionCompanies = $this->permissionCompanies;
        $permissionGroupCompanies = $this->permissionGroupCompanies;
        $admin = 1;

        $directory = 'report/excel'; // Direktori tempat file akan disimpan
        $date = now()->format('dmY');
        $reportName = 'Nama Report';
        $fileName = $reportType.'_'.$date.'.xlsx'; // Nama file yang akan disimpan

        if($reportType==='Goal'){
            $export = new GoalExport($period, $groupCompany, $location, $company, $admin, $permissionLocations, $permissionCompanies, $permissionGroupCompanies);
            $fileContent = Excel::download($export, $fileName)->getFile();
        }
        return false;

        // Mengecek dan membuat direktori jika belum ada
        if (!Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory, 0755, true); // Buat direktori dengan izin 0755 (opsional)
        }

        // Menyimpan file ke dalam direktori yang sudah ada
        Storage::disk('public')->put($directory . '/' . $fileName, $fileContent);

        // Simpan informasi report ke dalam database
        $filePath = $directory . '/' . $fileName;
        $report = new Report();
        $report->name = $reportName;
        $report->file_path = $filePath;
        $report->save();

        return redirect()->back()->with('success', 'Report berhasil di-generate dan disimpan.');
    }

}
