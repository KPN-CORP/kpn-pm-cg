<?php

namespace App\Http\Controllers;

use App\Exports\InvalidApprovalAppraisalImport;
use App\Imports\ApprovalLayerAppraisalImport;
use Illuminate\Http\Request;
use App\Models\ApprovalLayer; 
use App\Models\ApprovalRequest;
use App\Models\Approval;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use RealRashid\SweetAlert\Facades\Alert;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ApprovalLayerImport;
use App\Models\Appraisal;
use App\Models\AppraisalContributor;
use App\Models\ApprovalLayerAppraisal;
use App\Models\ApprovalLayerAppraisalBackup;
use Illuminate\Support\Facades\Log;
use App\Models\ApprovalLayerBackup;
use App\Models\Calibration;
use App\Models\EmployeeAppraisal;
use App\Models\Goal;
use App\Services\AppService;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LayerController extends Controller
{
    protected $category;
    protected $user;
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->appService = $appService;
        $this->user = Auth::user()->employee_id;
        $this->category = 'Goals';
    }

    function layer() {

        $roles = Auth::user()->roles;

        $restrictionData = [];
        if(!is_null($roles)){
            $restrictionData = json_decode($roles->first()->restriction, true);
        }
        
        $permissionGroupCompanies = $restrictionData['group_company'] ?? [];
        $permissionCompanies = $restrictionData['contribution_level_code'] ?? [];
        $permissionLocations = $restrictionData['work_area_code'] ?? [];

        $criteria = [
            'work_area_code' => $permissionLocations,
            'group_company' => $permissionGroupCompanies,
            'contribution_level_code' => $permissionCompanies,
        ];

        $parentLink = 'Settings';
        $link = 'Layers';

        foreach ($criteria as $key => $value) {
            if (!is_array($value)) {
                $criteria[$key] = (array) $value;
            }
        }
        
        // $approvalLayers = DB::table('approval_layers as al')
        // ->select('al.employee_id', 'emp.fullname', 'emp.job_level', 'emp.contribution_level_code', 'emp.group_company', 'emp.office_area')
        // ->selectRaw("GROUP_CONCAT(al.layer ORDER BY al.layer ASC SEPARATOR '|') AS layers")
        // ->selectRaw("GROUP_CONCAT(al.approver_id ORDER BY al.layer ASC SEPARATOR '|') AS approver_ids")
        // ->selectRaw("GROUP_CONCAT(emp1.fullname ORDER BY al.layer ASC SEPARATOR '|') AS approver_names")
        // ->selectRaw("GROUP_CONCAT(emp1.job_level ORDER BY al.layer ASC SEPARATOR '|') AS approver_job_levels")
        // ->leftJoin('employees as emp', 'emp.employee_id', '=', 'al.employee_id')
        // ->leftJoin('employees as emp1', 'emp1.employee_id', '=', 'al.approver_id')
        // ->whereNull('emp.deleted_at') // Add condition to check if deleted_at is null
        // ->groupBy('al.employee_id', 'emp.fullname', 'emp.job_level', 'emp.contribution_level_code', 'emp.group_company', 'emp.office_area')
        // ->orderBy('emp.fullname')
        // ->when(!empty($criteria), function ($query) use ($criteria) {
        //     $query->where(function ($query) use ($criteria) {
        //     foreach ($criteria as $key => $values) {
        //         if (!empty($values)) {
        //         $query->whereIn("emp.$key", $values);
        //         }
        //     }
        //     });
        // })
        // ->get();

        $approvalLayers = DB::table('approval_layers as al')
            ->select(
                'al.employee_id',
                DB::raw("GROUP_CONCAT(al.layer ORDER BY al.layer ASC SEPARATOR '|') AS layers"),
                DB::raw("GROUP_CONCAT(al.approver_id ORDER BY al.layer ASC SEPARATOR '|') AS approver_ids")
            )
            ->groupBy('al.employee_id')
            ->get();
        $employeeIds = collect();

        foreach ($approvalLayers as $row) {
            $employeeIds->push($row->employee_id);

            if ($row->approver_ids) {
                $employeeIds = $employeeIds->merge(explode('|', $row->approver_ids));
            }
        }

        $employeeIds = $employeeIds->unique()->values();

        $employees = Employee::on('kpncorp')
            ->whereIn('employee_id', $employeeIds)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('employee_id');
        $approvalLayers->transform(function ($item) use ($employees) {

            $approverNames = [];
            $approverJobLevels = [];

            if ($item->approver_ids) {
                foreach (explode('|', $item->approver_ids) as $approverId) {
                    $emp = $employees->get($approverId);

                    $approverNames[] = $emp->fullname ?? '-';
                    $approverJobLevels[] = $emp->job_level ?? '-';
                }
            }

            $emp = $employees->get($item->employee_id);

            $item->fullname = $emp->fullname ?? '-';
            $item->job_level = $emp->job_level ?? '-';
            $item->contribution_level_code = $emp->contribution_level_code ?? '-';
            $item->group_company = $emp->group_company ?? '-';
            $item->office_area = $emp->office_area ?? '-';

            // 🔥 FIELD YANG SEBELUMNYA DARI SQL
            $item->approver_names = implode('|', $approverNames);
            $item->approver_job_levels = implode('|', $approverJobLevels);

            return $item;
        });

        $employees = Employee::select('employee_id', 'fullname')
        ->whereNotIn('job_level', ['2A', '2B', '2C', '2D', '3A', '3B','4A'])
        ->orderBy('fullname', 'asc')
        ->get();

    $employeeCount = $approvalLayers->unique('employee_id')->count();
        return view('pages.layers.layer', [
            'parentLink' => $parentLink,
            'link' => $link,
            'approvalLayers' => $approvalLayers,
            'employeeCount' => $employeeCount,
            'employees' => $employees,
        ]);
    }

    function updatelayer(Request $req) {
        $employeeId = $req->input('employee_id');
        $nikApps = $req->input('nik_app');
        $jumlahNikApp = count($nikApps);
        $userId = Auth::id();

        $approvalRequest = Goal::select('id')->where('employee_id', $employeeId)->first();

        $approvalLayersToDelete = ApprovalLayer::where('employee_id', $employeeId)->get();

        foreach ($approvalLayersToDelete as $layer) {
            ApprovalLayerBackup::create([
                'employee_id' => $layer->employee_id,
                'approver_id' => $layer->approver_id,
                'layer' => $layer->layer,
                'updated_by' => $layer->updated_by,
                'created_at' => $layer->created_at,
                'updated_at' => $layer->updated_at,
            ]);
        }

        ApprovalLayer::where('employee_id', $employeeId)->delete();

        //$cek="";
        $layer=1;
        for ($jml=0; $jml < $jumlahNikApp; $jml++) {
            $approverId = $nikApps[$jml];

            if($approverId<>''){
                ApprovalLayer::create([
                    'employee_id' => $employeeId,
                    'approver_id' => $approverId,
                    'layer' => $layer,
                    'updated_by' => $userId
                ]);    

                if($layer===1){
                    $cekApprovalRequest = ApprovalRequest::where('category', $this->category)
                                         ->where('employee_id', $employeeId)
                                         ->where('status', ['pending', 'sendback'])
                                         ->get();

                    if ($cekApprovalRequest->isNotEmpty()) {
                        $approvalRequestIds = $cekApprovalRequest->pluck('id');
                
                        DB::transaction(function() use ($employeeId, $approverId, $approvalRequestIds) {
                            ApprovalRequest::where('category', $this->category)
                                           ->where('employee_id', $employeeId)
                                           ->where('status', ['pending', 'sendback'])
                                           ->update([
                                               'current_approval_id' => $approverId,
                                               'updated_by' => null,
                                               'updated_at' => null
                                           ]);
                
                            Approval::whereIn('request_id', $approvalRequestIds)
                                    ->delete();
                        });
                    }
                }
            }
            $layer++;
        }

        Alert::success('Success');
        return redirect()->intended(route('layer', absolute: false));
    }

    public function importLayer(Request $request)
    {
        $request->validate([
            'excelFile' => 'required|mimes:xlsx,xls,csv'
        ]);
        
        // Muat file Excel ke dalam array
        $rows = Excel::toArray([], $request->file('excelFile'));
        $data = $rows[0]; // Ambil sheet pertama
        $employeeIds = [];

        // Mulai dari indeks 1 untuk mengabaikan header
        for ($i = 1; $i < count($data); $i++) {
            $employeeIds[] = $data[$i][0];
        }

        $employeeIds = array_unique($employeeIds);

        // Ambil employee_ids dari data
        //$employeeIds = array_unique(array_column($data, 'employee_id'));

        if (!empty($employeeIds)) {
            // Backup data sebelum menghapus
            $approvalLayersToDelete = ApprovalLayer::whereIn('employee_id', $employeeIds)->get();

            foreach ($approvalLayersToDelete as $layer) {
                ApprovalLayerBackup::create([
                    'employee_id' => $layer->employee_id,
                    'approver_id' => $layer->approver_id,
                    'layer' => $layer->layer,
                    'updated_by' => $layer->updated_by,
                    'created_at' => $layer->created_at,
                    'updated_at' => $layer->updated_at,
                ]);
            }
            // Hapus data lama
            ApprovalLayer::whereIn('employee_id', $employeeIds)->delete();
        }
        $userId = Auth::id();
        // Import data baru
        //Excel::import(new ApprovalLayerImport, $request->file('excelFile'));
        // Excel::import(new ApprovalLayerImport($userId), $request->file('excelFile'));

        // return back()->with('success', 'Data imported successfully.');
        $import = new ApprovalLayerImport($userId);
        Excel::import($import, $request->file('excelFile'));

        // Ambil ID karyawan yang memiliki layer lebih dari 6
        $invalidEmployees = $import->getInvalidEmployees();

        // Format pesan umpan balik
        $message = 'Data imported successfully.';
        if (!empty($invalidEmployees)) {
            $message .= '\nThe following employee IDs have layers greater than 6 and were not imported: \n' . implode(', ', $invalidEmployees);
        }

        return back()->with('success', $message);
    }

    public function show(Request $request)
    {
        $employeeId = $request->input('employee_id');

        // =========================
        // 1. Ambil layer data (DB utama)
        // =========================
        $approvalLayers1 = DB::table('approval_layer_backups as al')
            ->select(
                'al.employee_id',
                'al.updated_by',
                'al.updated_at',
                DB::raw("GROUP_CONCAT(al.layer ORDER BY al.layer ASC SEPARATOR '|') AS layers"),
                DB::raw("GROUP_CONCAT(al.approver_id ORDER BY al.layer ASC SEPARATOR '|') AS approver_ids")
            )
            ->where('al.employee_id', $employeeId)
            ->groupBy('al.employee_id', 'al.updated_by', 'al.updated_at')
            ->orderBy('al.updated_at', 'desc')
            ->get();

        $approvalLayers2 = DB::table('approval_layers as al')
            ->select(
                'al.employee_id',
                'al.updated_by',
                'al.updated_at',
                DB::raw("GROUP_CONCAT(al.layer ORDER BY al.layer ASC SEPARATOR '|') AS layers"),
                DB::raw("GROUP_CONCAT(al.approver_id ORDER BY al.layer ASC SEPARATOR '|') AS approver_ids")
            )
            ->where('al.employee_id', $employeeId)
            ->groupBy('al.employee_id', 'al.updated_by', 'al.updated_at')
            ->orderBy('al.updated_at', 'desc')
            ->get();

        $approvalLayers = $approvalLayers2->merge($approvalLayers1);

        // =========================
        // 2. Kumpulkan semua employee_id & approver_id
        // =========================
        $employeeIds = $approvalLayers
            ->pluck('employee_id')
            ->merge(
                $approvalLayers->pluck('approver_ids')
                    ->filter()
                    ->flatMap(fn ($ids) => explode('|', $ids))
            )
            ->unique()
            ->values();

        // =========================
        // 3. Ambil EMPLOYEES dari kpncorp
        // =========================
        $employees = DB::connection('kpncorp')
            ->table('employees')
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        // =========================
        // 4. Ambil USERS (updated_by) dari kpncorp
        // =========================
        $userIds = $approvalLayers
            ->pluck('updated_by')
            ->filter()
            ->unique()
            ->values();

        $users = DB::connection('kpncorp')
            ->table('users')
            ->whereIn('id', $userIds)
            ->get()
            ->keyBy('id');

        // =========================
        // 5. Mapping hasil akhir
        // =========================
        $approvalLayers = $approvalLayers->map(function ($item) use ($employees, $users) {

            // Employee utama
            $item->fullname = $employees[$item->employee_id]->fullname ?? '-';

            // Approver names
            $approverIds = explode('|', $item->approver_ids ?? '');
            $item->approver_names = collect($approverIds)
                ->map(fn ($id) => $employees[$id]->fullname ?? '-')
                ->implode('|');

            // Updated by name (USER)
            $item->updated_by_name = $users[$item->updated_by]->name ?? '-';

            return $item;
        });

        return response()->json($approvalLayers);
    }


    function layerAppraisal() {

        $roles = Auth::user()->roles;

        $period = $this->appService->appraisalPeriod();

        $restrictionData = [];
        if(!is_null($roles)){
            $restrictionData = json_decode($roles->first()->restriction, true);
        }
        
        $permissionGroupCompanies = $restrictionData['group_company'] ?? [];
        $permissionCompanies = $restrictionData['contribution_level_code'] ?? [];
        $permissionLocations = $restrictionData['work_area_code'] ?? [];

        $criteria = [
            'work_area_code' => $permissionLocations,
            'group_company' => $permissionGroupCompanies,
            'contribution_level_code' => $permissionCompanies,
        ];

        $parentLink = 'Settings';
        $link = 'Layers';

        $query = EmployeeAppraisal::with(['calibration' => function($query) use ($period) {
            $query->where('status', 'Approved')->where('period', $period); // Filter only 'Approved' calibrations
        }])
        ->select('fullname', 'employee_id', 'group_company', 'designation', 'company_name', 'contribution_level_code', 'work_area_code', 'office_area', 'unit');

        $query->where(function ($query) use ($criteria) {
            foreach ($criteria as $key => $value) {
            if ($value !== null && !empty($value)) {
                $query->whereIn($key, $value);
            }
            }
        });

        $query->whereNull('deleted_at'); // Add condition to check if deleted_at is null

        $datas = $query->get();
        
        return view('pages.layers.layer-appraisal', [
            'parentLink' => $parentLink,
            'link' => $link,
            'datas' => $datas,
        ]);
    }

    function layerAppraisalEdit(Request $request) {

        $roles = Auth::user()->roles;

        $restrictionData = [];
        if(!is_null($roles)){
            $restrictionData = json_decode($roles->first()->restriction, true);
        }
        
        $permissionGroupCompanies = $restrictionData['group_company'] ?? [];
        $permissionCompanies = $restrictionData['contribution_level_code'] ?? [];
        $permissionLocations = $restrictionData['work_area_code'] ?? [];

        $criteria = [
            'work_area_code' => $permissionLocations,
            'group_company' => $permissionGroupCompanies,
            'contribution_level_code' => $permissionCompanies,
        ];

        $parentLink = 'Settings';
        $link = 'Layers';

        foreach ($criteria as $key => $value) {
            if (!is_array($value)) {
                $criteria[$key] = (array) $value;
            }
        }

        $datas = EmployeeAppraisal::select('fullname', 'employee_id', 'date_of_joining', 'group_company', 'company_name', 'unit', 'designation', 'office_area')->with(['appraisalLayer' => function($query) {
            $query->with(['approver' => function($subquery) {
                $subquery->select('fullname', 'employee_id', 'designation');
            }])->select('employee_id', 'approver_id', 'layer_type', 'layer');
        }])
        ->where('employee_id', $request->id)
        ->first();

        if ($datas) {
            $datas->formattedDoj = $this->appService->formatDate($datas->date_of_joining);
        }

        $groupLayers = $datas->appraisalLayer->groupBy('layer_type')->map(function ($layers) {
            return $layers->sortBy('layer')->values();
        });

        $calibratorCount = isset($groupLayers['calibrator']) ? $groupLayers['calibrator']->count() : 1;

        $employee = EmployeeAppraisal::select('fullname', 'employee_id', 'designation')->get();

        return view('pages.layers.layer-appraisal-edit', [
            'parentLink' => $parentLink,
            'link' => $link,
            'datas' => $datas,
            'calibratorCount' => $calibratorCount,
            'groupLayers' => $groupLayers,
            'employee' => $employee,
        ]);
    }

    public function layerAppraisalUpdate(Request $request)
    {
        $userId = Auth::id();
        $period = $this->appService->appraisalPeriod();

        // Define validation rules
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|string',
            'manager' => 'nullable|string|exists:employees,employee_id',
            'peers' => 'nullable|array',
            'peers.*' => 'nullable|string|exists:employees,employee_id', // Validate each peer ID
            'subs' => 'nullable|array',
            'subs.*' => 'nullable|string|exists:employees,employee_id', // Validate each subordinate ID
            'calibrators' => 'nullable|array',
            'calibrators.*' => 'nullable|string|exists:employees,employee_id', // Validate each calibrator ID
        ]);

        // Check if the validation fails
        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput(); // Return validation errors
        }

        // Retrieve validated data
        $validated = $validator->validated();

        // Manager data
        $manager = $validated['manager'] ?? null;
        $peers = $validated['peers'] ?? [];
        $subs = $validated['subs'] ?? [];
        $calibrators = $validated['calibrators'] ?? [];

        $currentLayers = ApprovalLayerAppraisal::with(['approver'])->where('employee_id', $validated['employee_id'])
                                       ->whereIn('layer_type', ['manager', 'peers', 'subordinate'])
                                       ->get();
        $currentPeers = $currentLayers->where('layer_type', 'peers');

        // Get the manager (assuming there is only one)
        $currentManager = $currentLayers->where('layer_type', 'manager')->first();

        // Get all peers (already limited by the database to a max of 3)
        $currentPeers = $currentLayers->where('layer_type', 'peers')->pluck('approver.employee_id')->toArray();

        $peersToDelete = array_diff($currentPeers, $peers);
        
        // Get all subordinates (already limited by the database to a max of 3)
        $currentSub = $currentLayers->where('layer_type', 'subordinate')->pluck('approver.employee_id')->toArray();
        $subsToDelete = array_diff($currentSub, $subs);

        // return response()->json($peersToDelete);

        
        $approvalRequest = ApprovalRequest::where('employee_id', $validated['employee_id'])->where('category', 'Appraisal')->where('period', $period)->first();

        // $firstNonNullValue = reset(array_filter($calibrators));

        $filteredCalibrator = array_filter($calibrators, fn($value) => !is_null($value) && $value !== '');

        // Now reset the filtered array to get the first non-null, non-empty value
        $firstNonNullCalibrator = reset($filteredCalibrator);

        if ($approvalRequest) {
            $checkCalibration = Calibration::where('appraisal_id', $approvalRequest->form_id)->where('created_by', $currentManager->approver->id)->where('status', 'Pending')->first();

            // Check if a record was found, then update `approver_id` and `updated_by` fields
            if ($checkCalibration && $checkCalibration->approver_id != $firstNonNullCalibrator) {
                $checkCalibration->approver_id = $firstNonNullCalibrator; // Assign the new approver ID
                $checkCalibration->updated_by = Auth::id(); // Set the current authenticated user as `updated_by`
                $checkCalibration->save(); // Save changes to the database
            }
        }
        
        if ($currentManager) {
            if($currentManager->approver_id != $manager){
                // Check if the employee record exists
                if ($approvalRequest) {

                    $appraisal = Appraisal::where('id', $approvalRequest->form_id)->where('created_by', $currentManager->approver->id)->first();
        
                    if ($approvalRequest->created_by == $currentManager->approver->id) {
                        // Soft delete the record
                        $approvalRequest->delete();

                        if ($appraisal) {
                            // Soft delete the record
                            $appraisal->delete();
                            
                        }
                        
                    } else {
                        // Update the current_approval_id if not created by the current user
                        $approvalRequest->update([
                            'current_approval_id' => $manager,
                            'status' => 'Pending'
                        ]);
                        
                    }
        
                    $calibration = Calibration::where('appraisal_id', $approvalRequest->form_id)->where('created_by', $currentManager->approver->id)->where('status', 'Pending')->first();
        
                    if ($calibration) {
                        // Soft delete the record
                        $calibration->delete();
                        
                    }
        
                    $contributor = AppraisalContributor::where('appraisal_id', $approvalRequest->form_id)->where('contributor_id', $currentManager->approver_id)->first();
        
                    if ($contributor) {
                        // Soft delete the record
                        $contributor->delete();
                        
                    }
        
                }
            }
        }

        if ($currentManager && $approvalRequest) {
            if($approvalRequest->created_by == $currentManager->approver->id){

            AppraisalContributor::where('appraisal_id', $approvalRequest->form_id)
                                ->whereIn('contributor_type', ['peers', 'subordinate'])
                                ->delete();

            }else{
                if (!empty($peersToDelete)) {
                    foreach ($peersToDelete as $peerId) {
                        // Find the record in the AppraisalContributor table based on appraisal_id and contributor_id
                        $contributor = AppraisalContributor::where('appraisal_id', $approvalRequest->form_id)
                            ->where('contributor_id', $peerId)
                            ->where('contributor_type', 'peers')
                            ->first();
                
                        // If a record is found, perform a soft delete
                        if ($contributor) {
                            $contributor->delete();
                        }
                    }
                }
        
                if (!empty($subsToDelete)) {
                    // Iterate through each peer that needs to be deleted
                    foreach ($subsToDelete as $subId) {
                        // Find the record in the AppraisalContributor table based on appraisal_id and contributor_id
                        $contributor = AppraisalContributor::where('appraisal_id', $approvalRequest->form_id)
                            ->where('contributor_id', $subId)
                            ->where('contributor_type', 'subordinate')
                            ->first();
                
                        // If a record is found, perform a soft delete
                        if ($contributor) {
                            $contributor->delete();
                        }
                    }
                }
            }
        }

        
        // Delete existing records for the employee_id
        ApprovalLayerAppraisal::where('employee_id', $validated['employee_id'])->delete();

        // Update manager
        if (!is_null($manager)) {
            ApprovalLayerAppraisal::create(
                [
                    'layer_type' => 'manager',
                    'employee_id' => $validated['employee_id'],
                    'approver_id' => $manager,
                    'created_by' => $userId
                ]
            );
        }

        // Update peers, ignoring null entries
        if (is_array($peers)) {
            foreach ($peers as $index => $peer) {
                if (!is_null($peer)) {
                    ApprovalLayerAppraisal::create(
                        [
                            'layer_type' => 'peers',
                            'layer' => $index + 1,
                            'employee_id' => $validated['employee_id'],
                            'approver_id' => $peer,
                            'created_by' => $userId
                        ]
                    );
                }
            }
        }

        // Update subs, ignoring null entries
        if (is_array($subs)) {
            foreach ($subs as $index => $sub) {
                if (!is_null($sub)) {
                    ApprovalLayerAppraisal::create(
                        [
                            'layer_type' => 'subordinate',
                            'layer' => $index + 1,
                            'employee_id' => $validated['employee_id'],
                            'approver_id' => $sub,
                            'created_by' => $userId
                        ]
                    );
                }
            }
        }

        // Update calibrators, ignoring null entries
        if (is_array($calibrators)) {
            foreach ($calibrators as $index => $calibrator) {
                if (!is_null($calibrator)) {
                    ApprovalLayerAppraisal::create(
                        [
                            'layer_type' => 'calibrator',
                            'layer' => $index + 1,
                            'employee_id' => $validated['employee_id'],
                            'approver_id' => $calibrator,
                            'created_by' => $userId
                        ]
                    );
                }
            }
        }

        // Redirect back to layer-appraisal page
        return redirect()->route('layer-appraisal')->with('success', 'Appraisal layers updated successfully.');
    }

    public function layerAppraisalImport(Request $request)
    {
        $period = $this->appService->appraisalPeriod();

        try {
            $request->validate([
                'excelFile' => 'required|mimes:xlsx,xls,csv'
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        try {
            // Get the ID of the currently authenticated user
            $userId = Auth::id();
    
            // Initialize the import process with the user ID and period
            $import = new ApprovalLayerAppraisalImport($userId, $period);
    
            // Perform the import
            Excel::import($import, $request->file('excelFile'));
    
            // Check for any invalid employees
            $invalidEmployees = $import->getInvalidEmployees();
            $message = 'Data imported successfully.';
    
            if (!empty($invalidEmployees)) {
                session()->put('invalid_employees', $invalidEmployees);
                $message .= ' With some errors. <a href="' . route('export.invalid.layer.appraisal') . '">Click here to download the list of errors.</a>';
            }
    
            return redirect()->back()->with('success', $message);
    
        } catch (ValidationException $e) {
            // Catch the validation exception and redirect back with a custom error message
            return redirect()->back()->with('error', $e->errors()['error'][0]);
        } catch (\Exception $e) {
            // Handle any other exceptions
            return redirect()->back()->with('error', 'An unexpected error occurred during the import process. Please try again.');
        }

    }

    public function exportInvalidLayerAppraisal()
    {
        // Retrieve the invalid employees from the session or another source
        $invalidEmployees = session('invalid_employees');

        if (empty($invalidEmployees)) {
            return redirect()->back()->with('success', 'No invalid employees to export.');
        }

        // Export the invalid employees to an Excel file
        return Excel::download(new InvalidApprovalAppraisalImport($invalidEmployees), 'errors_layer_import.xlsx');
    }

    public function getEmployeeLayerDetails($employeeId)
    {
        try {
            // Validate employee ID format (assuming numeric for this example)
            if (!is_numeric($employeeId)) {
                return response()->json(['error' => 'Invalid employee ID format'], 400);
            }

            // Cache the employee data to reduce database queries
            $employee = Cache::remember("employee_{$employeeId}", 60, function () use ($employeeId) {
                return EmployeeAppraisal::where('employee_id', $employeeId)->firstOrFail();
            });

            $doj = Carbon::parse($employee->date_of_joining);

            // Fetch history (you can also cache this if necessary)
            $history = ApprovalLayerAppraisal::with(['approver', 'createBy', 'updateBy'])->where('employee_id', $employeeId)->get();

            // Build the response data structure
            $data = [
                'fullname' => $employee->fullname,
                'employee_id' => $employee->employee_id,
                'formattedDoj' => $doj->format('d M Y'),
                'group_company' => $employee->group_company,
                'company_name' => $employee->company_name,
                'unit' => $employee->unit,
                'designation' => $employee->designation_name,
                'office_area' => $employee->office_area,
                'history' => $history->map(function($entry) {
                    return [
                        'layer_type' => $entry->layer_type,
                        'layer' => $entry->layer,
                        'fullname' => $entry->approver->fullname,
                        'employee_id' => $entry->approver->employee_id,
                        'updated_by' => $entry->createBy ? $entry->createBy->fullname.' ('. $entry->createBy->employee_id .')' : ($entry->updateBy ? $entry->updateBy->fullname.' ('. $entry->updateBy->employee_id .')' : 'System') ,
                        'updated_at' => $entry->updated_at ? $entry->updated_at->format('Y-m-d H:i:s') : null,
                    ];
                }),
            ];

            return response()->json($data);
        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Employee not found'], 404);
        } catch (Exception $e) {
            return response()->json(['error' => 'An unexpected error occurred'], 500);
        }
    }

}