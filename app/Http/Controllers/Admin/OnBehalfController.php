<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UserExport;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\ApprovalLayer;
use App\Models\ApprovalLayerAppraisal;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSnapshots;
use App\Models\Calibration;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use App\Models\FormGroupAppraisal;
use App\Models\Goal;
use App\Models\KpiUnits;
use App\Models\Location;
use App\Models\MasterRating;
use App\Models\User;
use App\Services\AppService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use RealRashid\SweetAlert\Facades\Alert;
use stdClass;

class OnBehalfController extends Controller
{
    protected $groupCompanies;
    protected $companies;
    protected $locations;
    protected $permissionGroupCompanies;
    protected $permissionCompanies;
    protected $permissionLocations;
    protected $roles;
    protected $category;
    protected $appService;
    protected $user;
    
    public function __construct(AppService $appService)
    {
        $this->category = 'Goals';
        $this->appService = $appService;
        $this->roles = Auth::user()->roles;
        $this->user = Auth::user();
        
        $restrictionData = [];
        if(!is_null($this->roles)){
            $restrictionData = json_decode($this->roles->first()->restriction, true);
        }
        
        $this->permissionGroupCompanies = $restrictionData['group_company'] ?? [];
        $this->permissionCompanies = $restrictionData['contribution_level_code'] ?? [];
        $this->permissionLocations = $restrictionData['work_area_code'] ?? [];

        $groupCompanyCodes = $restrictionData['group_company'] ?? [];

        $this->groupCompanies = Employee::select('group_company')
            ->when(!empty($groupCompanyCodes), function ($query) use ($groupCompanyCodes) {
                return $query->whereIn('group_company', $groupCompanyCodes);
            })->orderBy('group_company')->distinct()->pluck('group_company');

        $workAreaCodes = $restrictionData['work_area_code'] ?? [];

        $this->locations = Employee::select('office_area', 'work_area_code', 'group_company')
            ->when(!empty($workAreaCodes) || !empty($groupCompanyCodes), function ($query) use ($workAreaCodes, $groupCompanyCodes) {
                return $query->where(function ($query) use ($workAreaCodes, $groupCompanyCodes) {
                    if (!empty($workAreaCodes)) {
                        $query->whereIn('work_area_code', $workAreaCodes);
                    }
                    if (!empty($groupCompanyCodes)) {
                        $query->orWhereIn('group_company', $groupCompanyCodes);
                    }
                });
            })
            ->orderBy('work_area_code')->distinct()->get();

        $companyCodes = $restrictionData['contribution_level_code'] ?? [];

        $this->companies = Company::select('contribution_level', 'contribution_level_code')
            ->when(!empty($companyCodes), function ($query) use ($companyCodes) {
                return $query->whereIn('contribution_level_code', $companyCodes);
            })
            ->orderBy('contribution_level_code')->get();
    }
    
    function index() {

        $parentLink = 'Admin';
        $link = 'On Behalf';

        $locations = $this->locations;
        $companies = $this->companies;
        $groupCompanies = $this->groupCompanies;

        return view('pages.onbehalfs.app', compact('link', 'parentLink', 'locations', 'companies', 'groupCompanies'));
       
    }

    public function getOnBehalfContent(Request $request)
    {
        $category = $request->input('category');
        // $category = $id;

        $filterCategory = $request->input('filter_category');
        // $filterCategory = $category;

        $permissionLocations = $this->permissionLocations;
        $permissionCompanies = $this->permissionCompanies;
        $permissionGroupCompanies = $this->permissionGroupCompanies;

        $group_company = $request->input('group_company', []);
        $location = $request->input('location', []);
        $company = $request->input('company', []);

        $filters = compact('group_company', 'location', 'company');

        $parentLink = 'Admin';
        $link = 'On Behalf';

        $data = [];

        ini_set('memory_limit', '512M');
        
        if ($filterCategory == 'Goals') {

            $period = $this->appService->goalPeriod();

            // Mengambil data pengajuan berdasarkan employee_id atau manager_id
            $datas = ApprovalRequest::with(['employee', 'goal', 'updatedBy', 'initiated', 'approval' => function ($query) {
                $query->with('approverName'); // Load nested relationship
            }])->where('category', $filterCategory)->where('period', $period)->whereHas('employee')->whereHas('goal');
            
            $criteria = [
                'work_area_code' => $permissionLocations,
                'group_company' => $permissionGroupCompanies,
                'contribution_level_code' => $permissionCompanies,
            ];

            $datas->where(function ($query) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if ($value !== null && !empty($value)) {
                        $query->orWhereHas('employee', function ($subquery) use ($key, $value) {
                            $subquery->whereIn($key, $value);
                        });
                    }
                }
            });
            
            // Apply filters based on request parameters
            if (!empty($group_company)) {
                $datas->whereHas('employee', function ($datas) use ($group_company) {
                    $datas->whereIn('group_company', $group_company);
                });
            }
            if (!empty($location)) {
                $datas->whereHas('employee', function ($datas) use ($location) {
                    $datas->whereIn('work_area_code', $location);
                });
            }
    
            if (!empty($company)) {
                $datas->whereHas('employee', function ($datas) use ($company) {
                    $datas->whereIn('contribution_level_code', $company);
                });
            }
            
            $datas = $datas->get();
            
            $datas->map(function($item) {

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
                    $item->name = $item->manager ? $item->manager->fullname . ' (' . $item->manager->employee_id . ')' : '';
                    $item->approvalLayer = ApprovalLayer::where('employee_id', $item->employee_id)
                                                        ->where('approver_id', $item->current_approval_id)
                                                        ->value('layer');
                }

                $access_menu = json_decode($item->employee->access_menu, true);
                $access = $access_menu['goals'] && $access_menu['doj'] ?? null;

                $item->access = $access;

                return $item;

                });
            
            foreach ($datas as $request) {
                // Memeriksa status form dan pembuatnya
                if ($request->goal->form_status != 'Draft' || $request->created_by == Auth::user()->id) {
                    // Mengambil nilai fullname dari relasi approverName
                    if ($request->approval->first()) {
                        $approverName = $request->approval->first();
                        $dataApprover = $approverName->approverName->fullname;
                    }else{
                        $dataApprover = '';
                    }
                    // Buat objek untuk menyimpan data request dan approver fullname
                    $dataItem = new stdClass();
                    $dataItem = $request;              
                    // Tambahkan objek $dataItem ke dalam array $data
                    $data[] = $dataItem;
                    
                }
            }
            Log::info('OnBehalf - Goals Data:', [
                'category' => $category,
                'filter_category' => $filterCategory,
                'count' => count($data),
                'data' => collect($data)->take(5), // hanya tampilkan 5 pertama untuk menghindari log berlebihan
            ]);
        }      

        if ($filterCategory == 'Appraisal') {

            $period = $this->appService->appraisalPeriod();

            $datas = ApprovalRequest::with([
                'employee',
                'appraisal',
                'updatedBy',
                'initiated',
                'calibration' => function ($query) {
                    $query->where('status', 'Pending');
                },
                'approval' => function ($query) {
                    $query->with('approverName');
                }
            ])
            ->where('category', $filterCategory)
            ->where('period', $period)
            ->whereHas('employee')
            ->whereHas('manager');

            // Apply permission-based filters
            $criteria = [
                'work_area_code' => $permissionLocations,
                'group_company' => $permissionGroupCompanies,
                'contribution_level_code' => $permissionCompanies,
            ];

            $datas->where(function ($query) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if (!empty($value)) {
                        $query->orWhereHas('employee', function ($subquery) use ($key, $value) {
                            $subquery->whereIn($key, $value);
                        });
                    }
                }
            });

            // Apply request filters
            if (!empty($group_company)) {
                $datas->whereHas('employee', function ($query) use ($group_company) {
                    $query->whereIn('group_company', $group_company);
                });
            }

            if (!empty($location)) {
                $datas->whereHas('employee', function ($query) use ($location) {
                    $query->whereIn('work_area_code', $location);
                });
            }

            if (!empty($company)) {
                $datas->whereHas('employee', function ($query) use ($company) {
                    $query->whereIn('contribution_level_code', $company);
                });
            }

            $datas = $datas->get();

            foreach ($datas as $request) {
                $appraisal = $request->appraisal;

                if (!$appraisal) continue; // Skip jika appraisal null

                // Cek form_status atau created_by
                if (($appraisal->goal->form_status ?? null) !== 'Draft' || $request->created_by == Auth::user()->id) {

                    $request->formatted_created_at = $this->appService->formatDate($appraisal->created_at);
                    $request->formatted_updated_at = $this->appService->formatDate($appraisal->updated_at);

                    if ($request->sendback_to == $request->employee->employee_id) {
                        $request->name = $request->employee->fullname . ' (' . $request->employee->employee_id . ')';
                        $request->approvalLayer = '';
                    } else {
                        $request->name = $request->manager->fullname . ' (' . $request->manager->employee_id . ')';
                        $request->approvalLayer = ApprovalLayerAppraisal::where('employee_id', $request->employee_id)
                            ->where('approver_id', $request->current_approval_id)
                            ->value('layer');
                    }

                    // Get final rating
                    $finalRating = null;
                    $formGroupId = $appraisal->form_group_id ?? null;

                    if ($formGroupId) {
                        $formGroup = FormGroupAppraisal::with('rating')->find($formGroupId);
                        if ($formGroup && $formGroup->rating) {
                            foreach ($formGroup->rating as $rating) {
                                if ((int)$rating->value === (int)$appraisal->rating) {
                                    $finalRating = $rating->parameter;
                                    break;
                                }
                            }
                        }
                    }

                    $dataApprover = $request->approval->first()->approverName->fullname ?? '';

                    $goalData = json_decode($request->appraisal->goal->form_data, true);

                    $form_data = Auth::user()->id == $request->appraisal->created_by ? $request->appraisal->approvalSnapshots->form_data : $request->appraisal->form_data;

                    $appraisalData = json_decode($form_data, true);

                    $employeeData = $request->employee;

                    $formData = $this->appService->combineFormData($appraisalData, $goalData, 'employee', $employeeData, $period);

                    // Simpan dalam objek stdClass
                    $dataItem = new \stdClass();
                    $dataItem->request = $request;
                    $dataItem->approver_name = $dataApprover;
                    $dataItem->name = $request->name;
                    $dataItem->approvalLayer = $request->approvalLayer;
                    $dataItem->finalRating = $finalRating;
                    $dataItem->formData = $formData;

                    $data[] = $dataItem;
                }
            }

            Log::info('OnBehalf - Appraisal Data:', [
                'category' => $category,
                'filter_category' => $filterCategory,
                'count' => count($data),
                'data' => collect($data)->take(5), // limit preview in log
            ]);
        }

        if ($filterCategory == 'Rating') {

            $period = $this->appService->appraisalPeriod();

            $datas = EmployeeAppraisal::whereHas('kpiUnit', function ($query) use ($period) {
                $query->where('periode', $period);
            });

            // Apply permission-based filters
            $criteria = [
                'work_area_code' => $permissionLocations,
                'group_company' => $permissionGroupCompanies,
                'contribution_level_code' => $permissionCompanies,
            ];

            $datas->where(function ($query) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if (!empty($value)) {
                        $query->whereIn($key, $value);
                    }
                }
            });

            // Apply request filters
            if (!empty($group_company)) {
                $datas->whereIn('group_company', $group_company);
            }

            if (!empty($location)) {
                $datas->whereIn('work_area_code', $location);
            }

            if (!empty($company)) {
                $datas->whereIn('contribution_level_code', $company);
            }

            $data = $datas->get();
            
            Log::info('OnBehalf - Rating Data:', [
                'category' => $category,
                'filter_category' => $filterCategory,
                'count' => count($data),
                'data' => collect($data)->take(5), // limit preview in log
            ]);
        }
    
        
        $locations = $this->locations;
        $companies = $this->companies;
        $groupCompanies = $this->groupCompanies;
        
        if ($filterCategory == 'Goals') {
            return view('pages.onbehalfs.goal', compact('data', 'link', 'parentLink', 'locations', 'companies', 'groupCompanies'));
        } elseif ($filterCategory == 'Appraisal') {
            return view('pages.onbehalfs.appraisal', compact('data', 'link', 'parentLink', 'locations', 'companies', 'groupCompanies'));
        } elseif ($filterCategory == 'Rating') {
            return view('pages.onbehalfs.calibrator', compact('data', 'link', 'parentLink', 'locations', 'companies', 'groupCompanies'));
        } else {
            return view('pages.onbehalfs.empty');
        }
    }

    function create($id) {

        // Mengambil data pengajuan berdasarkan employee_id atau manager_id
        $datas = ApprovalRequest::with(['employee', 'goal', 'manager', 'approval' => function ($query) {
            $query->with('approverName'); // Load nested relationship
        }])->where('form_id', $id)->get();

        $data = [];
        
        foreach ($datas as $request) {
            // Memeriksa status form dan pembuatnya
            if ($request->goal->form_status != 'Draft' || $request->created_by == Auth::user()->id) {
                // Mengambil nilai fullname dari relasi approverName
                if ($request->approval->first()) {
                    $approverName = $request->approval->first();
                    $dataApprover = $approverName->approverName->fullname;
                }else{
                    $dataApprover = '';
                }
        
                // Buat objek untuk menyimpan data request dan approver fullname
                $dataItem = new stdClass();

                $dataItem->request = $request;
                $dataItem->approver_name = $dataApprover;
              

                // Tambahkan objek $dataItem ke dalam array $data
                $data[] = $dataItem;
                
            }
        }
        
        $formData = [];
        if($datas->isNotEmpty()){
            $formData = json_decode($datas->first()->goal->form_data, true);
        }

        $path = base_path('resources/goal.json');

        // Check if the JSON file exists
        if (!File::exists($path)) {
            // Handle the situation where the JSON file doesn't exist
            abort(500, 'JSON file does not exist.');
        }

        // Read the contents of the JSON file
        $options = json_decode(File::get($path), true);

        $uomOption = $options['UoM'];
        $typeOption = $options['Type'];

        $parentLink = 'On Behalf';
        $link = 'Approval';

        return view('pages.onbehalfs.approval', compact('data', 'link', 'parentLink', 'formData', 'uomOption', 'typeOption'));

    }
    
    public function store(Request $request): RedirectResponse

    {
        // Inisialisasi array untuk menyimpan pesan validasi kustom

        $nextLayer = ApprovalLayer::where('approver_id', $request->current_approver_id)
                                    ->where('employee_id', $request->employee_id)->max('layer');

        // Cari approver_id pada layer selanjutnya
        $nextApprover = ApprovalLayer::where('layer', $nextLayer + 1)->where('employee_id', $request->employee_id)->value('approver_id');

        if (!$nextApprover) {
            $approver = $request->current_approver_id;
            $statusRequest = 'Approved';
            $statusForm = 'Approved';
        }else{
            $approver = $nextApprover;
            $statusRequest = 'Pending';
            $statusForm = 'Submitted';
        }

        $status = 'Approved';

        $customMessages = [];

        $kpis = $request->input('kpi', []);
        $targets = $request->input('target', []);
        $uoms = $request->input('uom', []);
        $weightages = $request->input('weightage', []);
        $descriptions = $request->input('description', []);
        $types = $request->input('type', []);
        $custom_uoms = $request->input('custom_uom', []);

        // Menyiapkan aturan validasi
        $rules = [
            'kpi.*' => 'required|string',
            'target.*' => 'required|string',
            'uom.*' => 'required|string',
            'weightage.*' => 'required|integer|min:5|max:100',
            'type.*' => 'required|string',
        ];

        // Pesan validasi kustom
        $customMessages = [
            'weightage.*.integer' => 'Weightage harus berupa angka.',
            'weightage.*.min' => 'Weightage harus lebih besar atau sama dengan :min %.',
            'weightage.*.max' => 'Weightage harus kurang dari atau sama dengan :max %.',
        ];

        // Membuat Validator instance
        if ($request->submit_type === 'submit_form') {
            $validator = Validator::make($request->all(), $rules, $customMessages);
    
            // Jika validasi gagal
            if ($validator->fails()) {
                return back()->withErrors($validator)->withInput();
            }
        }

        // Inisialisasi array untuk menyimpan data KPI
        
        $kpiData = [];
        // Reset nomor indeks untuk penggunaan berikutnya
        $index = 1;

        // Iterasi melalui input untuk mendapatkan data KPI
        foreach ($kpis as $index => $kpi) {
            // Memastikan ada nilai untuk semua input terkait
            if (isset($targets[$index], $uoms[$index], $weightages[$index], $types[$index])) {
                // Simpan data KPI ke dalam array dengan nomor indeks sebagai kunci
                if($custom_uoms[$index]){
                    $customuom = $custom_uoms[$index];
                }else{
                    $customuom = null;
                }
                
                $kpiData[$index] = [
                    'kpi' => $kpi,
                    'target' => $targets[$index],
                    'uom' => $uoms[$index],
                    'weightage' => $weightages[$index],
                    'description' => $descriptions[$index],
                    'type' => $types[$index],
                    'custom_uom' => $customuom
                ];

                $index++;
            }
        }

        // Simpan data KPI ke dalam file JSON
        $jsonData = json_encode($kpiData);

        $checkApprovalSnapshots = ApprovalSnapshots::where('form_id', $request->id)->where('employee_id', $request->current_approver_id)->first();

        if (!empty($checkApprovalSnapshots)) {
            $snapshot = ApprovalSnapshots::find($checkApprovalSnapshots->id);
            $snapshot->form_data = $jsonData;
            $snapshot->updated_by = Auth::user()->id;
        } else {
            $snapshot = new ApprovalSnapshots;
            $snapshot->id = Str::uuid();
            $snapshot->form_data = $jsonData;
            $snapshot->form_id = $request->id;
            $snapshot->employee_id = $request->current_approver_id;
            $snapshot->created_by = Auth::user()->id;

        }
        $snapshot->save();

        $model = Goal::find($request->id);
        $model->form_data = $jsonData;
        $model->form_status = $statusForm;
        
        $model->save();

        $approvalRequest = ApprovalRequest::where('form_id', $request->id)->first();
        $approvalRequest->current_approval_id = $approver;
        $approvalRequest->status = $statusRequest;
        $approvalRequest->updated_by = Auth::user()->id;
        $approvalRequest->messages = $request->messages;
        $approvalRequest->sendback_messages = "";
        // Set other attributes as needed
        $approvalRequest->save();

        $checkApproval = Approval::where('request_id', $approvalRequest->id)->where('approver_id', $request->current_approver_id)->first();

        if ($checkApproval) {
            $approval = $checkApproval;
            $approval->messages = $request->messages;

        } else {
            $approval = new Approval;
            $approval->request_id = $approvalRequest->id;
            $approval->approver_id = $request->current_approver_id;
            $approval->created_by = Auth::user()->id;
            $approval->status = $status;
            $approval->messages = $request->messages;
            // Set other attributes as needed
        }
        $approval->save();
            
        return redirect()->route('onbehalf');
    }

    public function unitOfMeasurement()
    {
        $uom = file_get_contents(base_path('resources/goal.json'));

        return response()->json(json_decode($uom, true));
    }

    public function sendback(Request $request, ApprovalRequest $approval)
    {
        $sendbackTo = $request->input('sendback_to');

        if ($sendbackTo === 'creator') {
            // Kirim kembali ke pembuat form (creator)
            $creator = $approval->user; // Pembuat form
            $previousApprovers = $creator->creatorApproverLayer->flatMap(function ($layer) {
                return $layer->previousApprovers;
            });
        } elseif ($sendbackTo === 'previous_approver') {
            // Kirim kembali ke atasan sebelumnya
            $previousApprovers = $approval->user->previousApprovers;
        }

        // Lakukan sesuatu dengan daftar previous_approvers, seperti menampilkannya di view
        return view('approval.sendback', compact('previousApprovers'));
    }

    public function getGoalContent(Request $request)
    {
        // Get the authenticated user's employee_id
        $user = Auth::user();

        $permissionLocations = $this->permissionLocations;
        $permissionCompanies = $this->permissionCompanies;
        $permissionGroupCompanies = $this->permissionGroupCompanies;

        $group_company = $request->input('group_company', []);
        $location = $request->input('location', []);
        $company = $request->input('company', []);

        $filters = compact('group_company', 'location', 'company');

        // Start building the query
        $query = ApprovalRequest::with(['employee', 'manager', 'goal', 'initiated'])->where('category', $this->category);

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
                $query->whereIn('group_company', $group_company);
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

        $path = base_path('resources/goal.json');

        // Check if the JSON file exists
        if (!File::exists($path)) {
            // Handle the situation where the JSON file doesn't exist
            abort(500, 'JSON file does not exist.');
        }

        // Read the contents of the JSON file
        $options = json_decode(File::get($path), true);

        $uomOption = $options['UoM'];
        $typeOption = $options['Type'];

        // Fetch the data based on the constructed query
        $data = $query->get();
        // Determine the report type and return the appropriate view
            return view('pages.onbehalfs.goal', compact('data', 'uomOption', 'typeOption'));
        
    }

    public function goalsRevoke(Request $request)
    {
        $goalId = $request->input('id');

        // Find the approval request record
        $approvalRequest = ApprovalRequest::where('form_id', $goalId)->first();
        $goals = Goal::where('id', $goalId)->first();
        $firstApprover = ApprovalLayer::where('employee_id', $approvalRequest->employee_id)->orderBy('layer', 'asc')
        ->value('approver_id');
        
        if (!$approvalRequest || !$goals) {
            return response()->json(['success' => false, 'message' => 'Goals not found.']);
        }

        try {
            // Process the revoke logic here
            $approvalRequest->sendback_to = $approvalRequest->employee_id;
            $approvalRequest->current_approval_id = $firstApprover;
            $approvalRequest->status = 'Sendback';
            $approvalRequest->save();

            $goals->form_status = 'Submitted';
            $goals->save();

            if ($goals) {
                Approval::where('request_id', $approvalRequest->id)->delete();
            }

            return response()->json(['success' => true, 'message' => 'Goal revoked successfully.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to revoke goal.']);
        }
    }

    public function rating($id) {
        $period = $this->appService->appraisalPeriod();
        $category = 'Appraisal';
        try {
            Log::info('Starting the rating on behalfs method.', ['user' => $this->user]);

            $amountOfTime = 100;
            ini_set('max_execution_time', $amountOfTime);
            $user = $id;
            $period = $this->appService->appraisalPeriod();

            // Get the KPI unit and calibration percentage
            $kpiUnit = KpiUnits::with(['masterCalibration' => function($query) use ($period) {
                $query->where('period', $period);
            }])->where('employee_id', $id)->where('status_aktif', 'T')->where('periode', $period)->first();

            if (!$kpiUnit) {
                Log::warning('KPI Unit not set for the user.', ['user' => $id]);
                Session::flash('error', "Your KPI Unit not been set");
                Session::flash('errorTitle', "Cannot Initiate Rating");
            }

            Log::info('Fetching KPI unit and calibration percentage.', ['user' => $id, 'period' => $period, 'kpiUnit' => $kpiUnit]);

            $calibration = $kpiUnit->masterCalibration->percentage;
            $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                ->where('id_rating_group', $kpiUnit->masterCalibration->id_rating_group)
                ->get();

            Log::info('Fetched master ratings.', ['masterRatingCount' => $masterRating->count()]);

            // Query for all ApprovalLayerAppraisal data
            $allData = ApprovalLayerAppraisal::with(['employee'])
                ->where('approver_id', $id)
                ->whereHas('employee', function ($query) {
                    // Ensure the employee's access_menu has accesspa = 1
                    $query->where(function($q) {
                        $q->whereRaw('json_valid(access_menu)')
                        ->whereJsonContains('access_menu', ['createpa' => 1]);
                    });
                })
                ->where('layer_type', 'calibrator')
                ->get();

            Log::info('Fetched all ApprovalLayerAppraisal data.', ['allDataCount' => $allData->count()]);

            // Query for ApprovalLayerAppraisal data with approval requests
            $dataWithRequests = ApprovalLayerAppraisal::join('approval_requests', 'approval_requests.employee_id', '=', 'approval_layer_appraisals.employee_id')
                ->where('approval_layer_appraisals.approver_id', $id)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $category)
                ->where('approval_requests.period', $period) // Apply $period to the relation
                ->whereNull('approval_requests.deleted_at')
                ->select('approval_layer_appraisals.*')
                ->get()
                ->keyBy('id');  // This will create a collection indexed by the 'id'

            Log::info('Fetched ApprovalLayerAppraisal data with requests.', ['dataWithRequestsCount' => $dataWithRequests->count()]);

            // Group the data based on job levels
            $datas = $allData->groupBy(function ($data) {
                $jobLevel = $data->employee->job_level;
                // if (in_array($jobLevel, ['2A', '2B', '2C', '2D', '3A', '3B'])) {
                //     return 'Level23';
                // } elseif (in_array($jobLevel, ['4A', '4B', '5A', '5B'])) {
                //     return 'Level45';
                // } elseif (in_array($jobLevel, ['6A', '6B', '7A', '7B'])) {
                //     return 'Level67';
                // } elseif (in_array($jobLevel, ['8A', '8B', '9A', '9B'])) {
                //     return 'Level89';
                // }
                return 'AllLevels';
            })->map(function ($group) use ($dataWithRequests, $id, $period, $category) {
                Log::info('Processing group.', ['groupSize' => $group->count()]);

                // Fetch `withRequests` based on the user's criteria
                $withRequests = ApprovalLayerAppraisal::join('approval_requests', 'approval_requests.employee_id', '=', 'approval_layer_appraisals.employee_id')
                    ->where('approval_layer_appraisals.approver_id', $id)
                    ->where('approval_layer_appraisals.layer_type', 'calibrator')
                    ->where('approval_requests.category', $category)
                    ->where('approval_requests.period', $period) // Apply $period to the relation
                    ->whereNull('approval_requests.deleted_at')
                    ->whereIn('approval_layer_appraisals.id', $group->pluck('id'))
                    ->select('approval_layer_appraisals.*', 'approval_requests.*')
                    ->get()
                    ->groupBy('id')
                    ->map(function ($subgroup) {
                        $appraisal = $subgroup->first();
                        $appraisal->approval_requests = $subgroup->first();
                        return $appraisal;
                    });

                Log::info('Processed withRequests.', ['withRequestsCount' => $withRequests->count()]);

                // Filter out items without requests
                $withoutRequests = $group->filter(function ($item) use ($dataWithRequests) {
                    return !$dataWithRequests->has($item->id);
                });

                Log::info('Processed withoutRequests.', ['withoutRequestsCount' => $withoutRequests->count()]);

                return [
                    'with_requests' => $withRequests->values(),
                    'without_requests' => $withoutRequests->values(),
                ];
            })->sortKeys();

            Log::info('Grouped and processed data.', ['groupedDataCount' => $datas->count()]);

            // Process rating data
            $ratingDatas = $datas->map(function ($group) use ($id, $period, $user) {
                Log::info('Processing rating data for group.', ['groupSize' => $group['with_requests']->count() + $group['without_requests']->count()]);

                // Preload all calibration data in bulk
                $calibration = Calibration::with(['approver'])->where('period', $period)
                ->whereIn('employee_id', $group['with_requests']->pluck('employee_id'))
                ->whereIn('appraisal_id', $group['with_requests']->pluck('approvalRequest')->flatten()->pluck('form_id'))
                ->whereIn('status', ['Pending', 'Approved'])
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy(['employee_id', 'appraisal_id']); // Group by employee_id and appraisal_id for easy access

                // Preload suggested ratings and rating values in bulk
                $suggestedRatings = [];
                $ratingValues = [];
                foreach ($group['with_requests'] as $data) {
                $employeeId = $data->employee->employee_id;
                $formId = $formId = $data->approvalRequest->where('category', 'Appraisal')->where('period', $period)->first()->form_id;

                // Cache suggested ratings
                if (!isset($suggestedRatings[$employeeId][$formId])) {
                    $suggestedRatings[$employeeId][$formId] = $this->appService->suggestedRating($employeeId, $formId, $period);
                }

                // Cache rating values
                if (!isset($ratingValues[$employeeId])) {
                    $ratingValues[$employeeId] = $this->appService->ratingValue($employeeId, $user, $period);
                }
                }

                // Process withRequests using preloaded data
                $withRequests = $group['with_requests']->map(function ($data) use ($id, $calibration, $suggestedRatings, $ratingValues, $period) {
                    Log::info('Processing withRequests item.', ['itemId' => $data->id]);

                    $employeeId = $data->employee->employee_id;
                    $formId = $data->approvalRequest->where('category', 'Appraisal')->where('period', $period)->first()->form_id;

                    // Fetch calibration data for the current employee and appraisal
                    $calibrationData = $calibration[$employeeId][$formId] ?? collect();

                    // Find previous rating
                    $previousRating = $calibrationData->whereNotNull('rating')
                        ->where('approver_id', '!=', $id)
                        ->first();

                    // Calculate suggested rating
                    $suggestedRating = $suggestedRatings[$employeeId][$formId];
                    $data->suggested_rating = $calibrationData->where('approver_id', $id)->first()
                        ? $this->appService->convertRating(
                            $suggestedRating,
                            $calibrationData->where('approver_id', $id)->first()->id_calibration_group
                        )
                        : null;

                    // Set previous rating details
                    $data->previous_rating = $previousRating
                        ? $this->appService->convertRating($previousRating->rating, $calibrationData->first()->id_calibration_group)
                        : null;
                    $data->previous_rating_name = $previousRating
                        ? $previousRating->approver->fullname . ' (' . $previousRating->approver->employee_id . ')'
                        : null;

                    // Set rating value
                    $data->rating_value = $ratingValues[$employeeId];


                    // Check if the user is a calibrator
                    $isCalibrator = $calibrationData->where('approver_id', $id)
                        ->where('status', 'Pending')
                        ->isNotEmpty();
                    $data->is_calibrator = $isCalibrator;

                    // Check if rating is allowed
                    $data->rating_allowed = $this->appService->ratingAllowedCheck($employeeId);

                    // Count incomplete ratings
                    $data->rating_incomplete = $calibrationData->whereNull('rating')->whereNull('deleted_at')->count();
                    $data->calibrationData = $calibrationData;

                    // Set rating status and approved date
                    $userCalibration = $calibrationData->first();
                    if ($userCalibration) {
                        $data->rating_status = $calibrationData->where('approver_id', $id)->first() ? $calibrationData->where('approver_id', $id)->first()->status : null;
                        $data->rating_approved_date = Carbon::parse($userCalibration->updated_at)->format('d M Y');
                    }

                    $data->onCalibratorPending = $calibrationData->where('approver_id', $id)->where('status', 'Pending')->count();

                    // Assign Pending and Approved Calibrators
                    $pendingCalibrator = $calibrationData->where('status', 'Pending')->first();
                    $approvedCalibrator = $calibrationData->where('status', 'Approved')->first();

                    $data->current_calibrator = $pendingCalibrator && $pendingCalibrator->approver
                        ? $pendingCalibrator->approver->fullname . ' (' . $pendingCalibrator->approver->employee_id . ')'
                        : false;
                    $data->approver_name = $approvedCalibrator && $approvedCalibrator->approver
                        ? $approvedCalibrator->approver->fullname . ' (' . $approvedCalibrator->approver->employee_id . ')'
                        : ($data->status == 'Pending' ? $data->approval_requests->approver->fullname : false);

                    return $data;
                });

                Log::info('Processed withRequests.', ['processedCount' => $withRequests->count()]);

                // Process `without_requests`
                $withoutRequests = $group['without_requests']->map(function ($data) use ($id, $calibration) {
                    Log::info('Processing withoutRequests item.', ['itemId' => $data->id]);

                    $data->suggested_rating = null;

                    $isCalibrator = Calibration::where('approver_id', $id)
                        ->where('employee_id', $data->employee->employee_id)
                        ->where('status', 'Pending')
                        ->exists();
                    $data->is_calibrator = $isCalibrator;

                    $data->rating_allowed = $this->appService->ratingAllowedCheck($data->employee->employee_id);

                    return $data;
                });

                Log::info('Processed withoutRequests.', ['processedCount' => $withoutRequests->count()]);

                $combinedResults = $withRequests->merge($withoutRequests);

                Log::info('Combined results.', ['combinedCount' => $combinedResults->count()]);

                return $combinedResults;
            });

            Log::info('Processed all rating data.', ['ratingDatasCount' => $ratingDatas->count()]);

            // Get calibration results
            $calibrations = $datas->map(function ($group) use ($calibration, $id) {
                Log::info('Processing calibration results for group.', ['groupSize' => $group['with_requests']->count() + $group['without_requests']->count()]);

                // $onCalibratorPending = $group['with_requests']->where('approver_id', $id)->where('status', 'Pending')->count();
                $calibratorPendingCount = $group['with_requests']->where('onCalibratorPending', '>', 0)->count();

                $countWithRequests = $group['with_requests']->count();
                $countWithoutRequests = $group['without_requests']->count();
                $count = $countWithRequests + $countWithoutRequests;
                // $count = 12; // Test number

                $ratingResults = [];
                $percentageResults = [];
                $calibration = json_decode($calibration, true);

                // Step 1: Calculate initial rating results and percentage results
                foreach ($calibration as $key => $weight) {
                    $ratingResults[$key] = round($count * $weight);
                    $percentageResults[$key] = round(100 * $weight);
                }

                // Step 2: Check if the sum of $ratingResults matches $count
                $totalRatingResults = array_sum($ratingResults);
                $difference = abs($count - $totalRatingResults);

                if ($difference !== 0) {
                    if ($totalRatingResults < $count) {
                        // Normalize the calibration weights to redistribute the difference
                        $totalWeight = array_sum($calibration);
                        $normalizedWeights = array_map(fn($w) => $w / $totalWeight, $calibration);

                        // Redistribute the difference proportionally based on normalized weights
                        foreach ($normalizedWeights as $key => $normalizedWeight) {
                            $adjustment = floor($difference * $normalizedWeight);
                            $ratingResults[$key] += $adjustment;
                        }

                        // Recalculate the total after redistribution to ensure it matches $count
                        $newTotal = array_sum($ratingResults);
                        if ($newTotal !== $count) {
                            // If there's still a small mismatch due to rounding, adjust the largest value
                            $maxWeightKey = array_keys($calibration, max($calibration))[0];
                            $ratingResults[$maxWeightKey] += ($count - $newTotal);
                        }
                    } elseif ($totalRatingResults > $count) {
                        // Allocate the $difference to the lowest $percentageResults that have $ratingResults value >= 1
                        while ($difference > 0) {
                            $lowestKey = collect($percentageResults)
                                ->filter(fn($percentage, $key) => $ratingResults[$key] >= 1)
                                ->sort()
                                ->keys()
                                ->first();

                            if ($lowestKey !== null) {
                                $ratingResults[$lowestKey] -= 1;
                                $difference -= 1;
                            } else {
                                break; // Exit if no valid key is found
                            }
                        }
                    }
                }

                // Step 3: Process suggested ratings and combine results
                $suggestedRatingCounts = $group['with_requests']->pluck('suggested_rating')->countBy();
                $totalSuggestedRatings = $suggestedRatingCounts->sum();

                $combinedResults = [];
                foreach ($calibration as $key => $weight) {
                    $ratingCount = $suggestedRatingCounts->get($key, 0);
                    $ratingPercentage = $totalSuggestedRatings > 0
                        ? round(($ratingCount / $totalSuggestedRatings) * 100, 2)
                        : 0;

                    $combinedResults[$key] = [
                        'percentage' => $percentageResults[$key] . '%',
                        'rating_count' => $ratingResults[$key],
                        'suggested_rating_count' => $ratingCount,
                        'suggested_rating_percentage' => $ratingPercentage . '%',
                    ];
                }

                Log::info('Processed calibration results.', ['combinedResults' => $combinedResults]);

                return [
                    'count' => $count,
                    'calibratorPendingCount' => $calibratorPendingCount,
                    'combined' => $combinedResults,
                ];
            });

            Log::info('Processed all calibration results.', ['calibrationsCount' => $calibrations->count()]);

            // Determine the active level as the first non-empty level
            $activeLevel = null;
            foreach ($calibrations as $level => $data) {
                if (!empty($data)) {
                    $activeLevel = $level;
                    break;
                }
            }

            Log::info('Determined active level.', ['activeLevel' => $activeLevel]);

            $parentLink = 'Calibration';
            $link = 'Rating';
            $id_calibration_group = $kpiUnit->masterCalibration->id_calibration_group;

            Log::info('Returning view with data.', ['activeLevel' => $activeLevel, 'id_calibration_group' => $id_calibration_group]);

            // dd($ratingDatas);

            return view('pages.rating.app', compact('ratingDatas', 'calibrations', 'masterRating', 'link', 'parentLink', 'activeLevel', 'id_calibration_group'));
        } catch (Exception $e) {
            Log::error('Error in index method: ' . $e->getMessage());
            return redirect()->route('onbehalf');
        }
    }

}
