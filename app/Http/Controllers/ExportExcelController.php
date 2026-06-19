<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeDetailExport;
use App\Exports\EmployeeExport;
use App\Exports\GoalExport;
use App\Exports\InitiatedExport;
use App\Exports\NotInitiatedExport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\EmployeepaExport;
use App\Jobs\GoalExportJob;
use App\Services\AppService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ExportExcelController extends Controller
{
    protected $permissionGroupCompanies;
    protected $permissionCompanies;
    protected $permissionLocations;
    protected $roles;
    protected $appService;
    
    public function __construct(AppService $appService)
    {
        $this->roles = Auth::user()->roles;
        $this->appService = $appService;
        
        $restrictionData = [];

        if(!$this->roles->isEmpty()){
            $restrictionData = json_decode($this->roles->first()->restriction, true);
        }
        
        $this->permissionGroupCompanies = $restrictionData['group_company'] ?? [];
        $this->permissionCompanies = $restrictionData['contribution_level_code'] ?? [];
        $this->permissionLocations = $restrictionData['work_area_code'] ?? [];

    }

    public function export(Request $request) 
    {
        $reportType = $request->export_report_type;
        $groupCompany = $request->export_group_company;
        $company = $request->export_company;
        $location = $request->export_location;
        $period = $request->export_period;

        $permissionGroupCompanies = $this->permissionGroupCompanies;
        $permissionCompanies = $this->permissionCompanies;
        $permissionLocations = $this->permissionLocations;

        $admin = 0;

        if($reportType==='Goal'){
            $goal = new GoalExport($period, $groupCompany, $location, $company, $admin, $permissionLocations, $permissionCompanies, $permissionGroupCompanies);
            return Excel::download($goal, 'goals.xlsx');
        }
        if($reportType==='Employee'){
            $employee = new EmployeeExport($groupCompany, $location, $company, $permissionLocations, $permissionCompanies, $permissionGroupCompanies);
            return Excel::download($employee, 'employee.xlsx');
        }
        return;

    }

    public function exportAdmin(Request $request) 
    {
        $reportType = $request->export_report_type;
        $groupCompany = $request->export_group_company;
        $company = $request->export_company;
        $location = $request->export_location;
        $period = $request->export_period;

        $permissionGroupCompanies = $this->permissionGroupCompanies;
        $permissionCompanies = $this->permissionCompanies;
        $permissionLocations = $this->permissionLocations;
        
        $admin = 1;

        if ($reportType === 'Goal') {
            $exportKey = 'goal_' . auth()->id() . '_' . now()->timestamp;

            GoalExportJob::dispatch(
                period:                   $period,
                groupCompany:             $groupCompany,
                location:                 $location,
                company:                  $company,
                admin:                    $admin,
                permissionLocations:      $permissionLocations,
                permissionCompanies:      $permissionCompanies,
                permissionGroupCompanies: $permissionGroupCompanies,
                requestedBy:              auth()->id(),
                exportKey:                $exportKey,
            );

            return redirect()
            ->back()
            ->with('toast', [
                'type' => 'info',
                'title' => 'Report Generation Started',
                'message' => 'Your report is being prepared in the background. The Report button will be available once the file is ready.'
            ]);
        }
        if($reportType==='Employee'){
            $employee = new EmployeeExport($groupCompany, $location, $company, $permissionLocations, $permissionCompanies, $permissionGroupCompanies);
            return Excel::download($employee, 'employee.xlsx');
        }
        if($reportType==='EmployeePA'){
            $employee = new EmployeepaExport($groupCompany, $location, $company, $permissionLocations, $permissionCompanies, $permissionGroupCompanies);
            return Excel::download($employee, 'employeePA.xlsx');
        }
        return;

    }

    public function latestGoalReport()
    {
        $userId = Auth::id();

        Log::debug('Latest Goal Report - Start', [
            'user_id' => $userId,
        ]);

        $allFiles = Storage::files('public/exports/goal');

        Log::debug('Latest Goal Report - All Files', [
            'count' => count($allFiles),
            'files' => $allFiles,
        ]);

        $files = collect($allFiles)
            ->filter(function ($file) use ($userId) {

                $matched = preg_match(
                    "/^public\/exports\/goal\/goal_{$userId}_\d+\.xlsx$/",
                    $file
                );

                Log::debug('Latest Goal Report - Checking File', [
                    'file' => $file,
                    'matched' => (bool) $matched,
                ]);

                return $matched;
            })
            ->sortDesc()
            ->values();

        Log::debug('Latest Goal Report - Filtered Files', [
            'count' => $files->count(),
            'files' => $files->toArray(),
        ]);

        if ($files->isEmpty()) {

            Log::debug('Latest Goal Report - No File Found', [
                'user_id' => $userId,
            ]);

            return response()->json([
                'exists' => false
            ]);
        }

        $file = $files->first();

        Log::debug('Latest Goal Report - Selected File', [
            'file' => $file,
            'basename' => basename($file),
        ]);

        return response()->json([
            'exists' => true,
            'file' => route('admin.export.download-existing', [
                'file' => basename($file)
            ])
        ]);
    }

    public function downloadExisting(string $file)
    {
        $userId = Auth::id();

        if (!preg_match("/^goal_{$userId}_\d+\.xlsx$/", $file)) {
            abort(403);
        }

        $path = "public/exports/goal/{$file}";

        abort_unless(Storage::exists($path), 404);

        return Storage::download($path);
    }

    public function notInitiated(Request $request) 
    {
        $employee_id = $request->employee_id;
        $period = $request->filterYear;

        $data = new NotInitiatedExport($employee_id, $period);
        return Excel::download($data, 'import_team_goals.xlsx');

    }

    public function initiated(Request $request) 
    {
        $employee_id = $request->employee_id;
        $period = $request->filterYear;

        $data = new InitiatedExport($employee_id, $period);
        return Excel::download($data, 'employee_initiated_goals.xlsx');

    }

    public function exportreportemp() 
    {
        return Excel::download(new EmployeeDetailExport, 'employees_detail.xlsx');
    }
}
