<?php

namespace App\Http\Controllers\Admin;

use App\Exports\UserExport;
use App\Http\Controllers\Controller;
use App\Models\Approval;
use App\Models\AppraisalContributor;
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
use App\Models\MasterCalibration;
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
        if (!is_null($this->roles)) {
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

    function index()
    {

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
            $datas = ApprovalRequest::with([
                'employee',
                'goal',
                'updatedBy',
                'initiated',
                'approval' => function ($query) {
                    $query->with('approverName'); // Load nested relationship
                }
            ])->where('category', $filterCategory)->where('period', $period)->whereHas('employee')->whereHas('goal');

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

            $datas->map(function ($item) {

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
                    } else {
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

                if (!$appraisal)
                    continue; // Skip jika appraisal null

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
                                if ((int) $rating->value === (int) $appraisal->rating) {
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

            $datas = EmployeeAppraisal::whereHas('isApproverAppraisal', function ($query) {
                $query->where('layer_type', 'calibrator');
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

    function create($id)
    {

        // Mengambil data pengajuan berdasarkan employee_id atau manager_id
        $datas = ApprovalRequest::with([
            'employee',
            'goal',
            'manager',
            'approval' => function ($query) {
                $query->with('approverName'); // Load nested relationship
            }
        ])->where('form_id', $id)->get();

        $data = [];

        foreach ($datas as $request) {
            // Memeriksa status form dan pembuatnya
            if ($request->goal->form_status != 'Draft' || $request->created_by == Auth::user()->id) {
                // Mengambil nilai fullname dari relasi approverName
                if ($request->approval->first()) {
                    $approverName = $request->approval->first();
                    $dataApprover = $approverName->approverName->fullname;
                } else {
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
        if ($datas->isNotEmpty()) {
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
        } else {
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
                if ($custom_uoms[$index]) {
                    $customuom = $custom_uoms[$index];
                } else {
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

    public function rating($id)
    {
        $category = 'Appraisal';
        try {
            Log::info('Starting the rating on behalfs method.', ['user' => $this->user]);

            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');

            $user = $id;
            $period = $this->appService->appraisalPeriod();

            $calibrationDistribution = '{"Exceptional":0.1,"Exceed Expectation":0.2,"Meet Expectation":0.4,"Need Improvement":0.2,"Poor":0.1}';

            // ─── 1. MASTER RATING (sekali, untuk semua) ───────────────────────────
            $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                ->where('id_rating_group', '30e4e9eb-476f-4914-a123-807958a95260')
                ->get();

            Log::info('Fetched master ratings.', ['masterRatingCount' => $masterRating->count()]);

            // ─── 2. Preload semua MasterCalibration & MasterRating untuk convertRating in-memory ───
            $allMasterCalibrations = MasterCalibration::all()->keyBy('id_calibration_group');
            $allMasterRatings = MasterRating::all();
            $masterRatingLowest = $allMasterRatings->sortBy('value')->first();

            // Helper closure: pengganti appService->convertRating() tanpa query DB
            $convertRatingLocal = function (float $value, $formID) use ($allMasterCalibrations, $allMasterRatings, $masterRatingLowest): ?string {
                $formGroup = $allMasterCalibrations->get($formID);
                if (!$formGroup)
                    return null;

                if ($value == 0) {
                    return $masterRatingLowest ? $masterRatingLowest->parameter : null;
                }

                $roundedValue = (int) round($value, 2);
                $idRatingGroup = $formGroup->id_rating_group;

                $condition = $allMasterRatings
                    ->where('id_rating_group', $idRatingGroup)
                    ->first(function ($r) use ($value, $roundedValue) {
                        $rangeMatch = $r->min_range <= $value && $r->max_range >= $value;
                        $exactMatch = $r->min_range == 0 && $r->max_range == 0 && (int) $r->value === $roundedValue;
                        return $rangeMatch || $exactMatch;
                    });

                return $condition ? $condition->parameter : null;
            };

            // ─── 3. Semua ApprovalLayerAppraisal milik calibrator ini ─────────────
            $allData = ApprovalLayerAppraisal::with(['employee'])
                ->where('approver_id', $id)
                ->whereHas('employee', function ($query) {
                    $query->where(function ($q) {
                        $q->whereRaw('json_valid(access_menu)')
                            ->whereJsonContains('access_menu', ['createpa' => 1]);
                    });
                })
                ->where('layer_type', 'calibrator')
                ->get();

            if ($allData->isEmpty()) {
                Session::flash('error', 'Cannot On Behalfs Rating PA Schedule has been closed');
                Session::flash('errorTitle', 'Cannot Initiate Rating');
                return back();
            }

            Log::info('Fetched all ApprovalLayerAppraisal data.', ['allDataCount' => $allData->count()]);

            // ─── 4. Query existence check: ALA ids yang punya approval_request ──
            // Pakai pluck agar tidak ada konflik kolom id
            $alaIdsWithRequests = ApprovalLayerAppraisal::join(
                'approval_requests',
                'approval_requests.employee_id',
                '=',
                'approval_layer_appraisals.employee_id'
            )
                ->where('approval_layer_appraisals.approver_id', $id)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $category)
                ->where('approval_requests.period', $period)
                ->whereNull('approval_requests.deleted_at')
                ->pluck('approval_layer_appraisals.id') // hanya ALA.id, tidak ada konflik
                ->flip();  // O(1) has() lookup

            // ─── 5. JOIN query lengkap: selectRaw agar ALA.id TIDAK tertimpa AR.id ─
            // Urutan: approval_layer_appraisals.* dulu, lalu approval_requests.*,
            // lalu approval_layer_appraisals.id as id (eksplisit override di akhir).
            $withRequestsRaw = ApprovalLayerAppraisal::join(
                'approval_requests',
                'approval_requests.employee_id',
                '=',
                'approval_layer_appraisals.employee_id'
            )
                ->where('approval_layer_appraisals.approver_id', $id)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $category)
                ->where('approval_requests.period', $period)
                ->whereNull('approval_requests.deleted_at')
                ->selectRaw('
                    approval_layer_appraisals.*,
                    approval_requests.*,
                    approval_layer_appraisals.id as id
                ')  // id di akhir = ALA.id (menimpa AR.id)
                ->get()
                ->groupBy('id')   // groupBy ALA.id yang sudah benar
                ->map(function ($subgroup) {
                    $appraisal = $subgroup->first();
                    $appraisal->approval_requests = $subgroup->first();
                    return $appraisal;
                });

            // keyBy ALA.id (sudah benar karena selectRaw override di atas)
            $dataWithRequestsById = $withRequestsRaw->keyBy('id');

            Log::info('Fetched withRequests data.', ['count' => $withRequestsRaw->count()]);

            // ─── 6. Kumpulkan employeeId & formId untuk preload ───────────────────
            $allEmployeeIds = $allData->pluck('employee.employee_id')->unique()->values()->toArray();

            // form_id langsung dari JOIN (approval_requests.form_id ada di model via SELECT *)
            // Tidak perlu lazy-load approvalRequest relation
            $allFormIds = $withRequestsRaw->pluck('form_id')->filter()->unique()->values()->toArray();

            // ─── 7. Preload Calibration untuk semua employee+form sekaligus ────────
            $allCalibrations = Calibration::with(['approver'])
                ->where('period', $period)
                ->whereIn('employee_id', $allEmployeeIds)
                ->whereIn('status', ['Pending', 'Approved'])
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy(['employee_id', 'appraisal_id']);

            // ─── 8. Preload ratingValue (Calibration Approved) untuk semua employee ─
            $allRatingValues = Calibration::select('employee_id', 'approver_id', 'rating', 'status', 'period', 'appraisal_id')
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('approver_id', $user)
                ->where('status', 'Approved')
                ->where('period', $period)
                ->get()
                ->keyBy('employee_id');

            // ─── 9. Preload ratingAllowedCheck untuk semua employee sekaligus ──────
            // Ambil semua ApprovalLayerAppraisal selain calibrator untuk seluruh employee
            $allApprovalLayers = ApprovalLayerAppraisal::with(['approver', 'employee'])
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('layer_type', '!=', 'calibrator')
                ->get()
                ->groupBy('employee_id');

            // Preload AppraisalContributor untuk semua kombinasi yg mungkin
            $allAppraisalContributors = AppraisalContributor::where('period', $period)
                ->whereIn('employee_id', $allEmployeeIds)
                ->get()
                ->groupBy(function ($item) {
                    return $item->employee_id . '|' . $item->contributor_id;
                });

            // Helper closure: pengganti appService->ratingAllowedCheck() tanpa query DB
            $ratingAllowedCheckLocal = function (string $employeeId) use ($allApprovalLayers, $allAppraisalContributors): array {
                $layers = $allApprovalLayers->get($employeeId, collect());
                $notFoundData = [];

                foreach ($layers as $approvalLayer) {
                    $review360 = json_decode($approvalLayer->employee->access_menu ?? '{}', true);
                    $key = $approvalLayer->employee_id . '|' . $approvalLayer->approver_id;
                    $exists = $allAppraisalContributors->has($key);

                    if (!$exists && isset($review360['review360']) && $review360['review360'] == 0) {
                        $notFoundData[] = [
                            'employee_id' => $approvalLayer->employee_id,
                            'approver_id' => $approvalLayer->approver_id,
                            'approver_name' => $approvalLayer->approver->fullname ?? '',
                            'layer_type' => $approvalLayer->layer_type,
                        ];
                    }
                }

                if (!empty($notFoundData)) {
                    return [
                        'status' => false,
                        'message' => '360 Review incomplete process',
                        'data' => $notFoundData,
                    ];
                }

                $review360Val = json_decode(
                    optional($layers->first())->employee->access_menu ?? '{}',
                    true
                )['review360'] ?? null;

                return [
                    'status' => true,
                    'message' => '360 Review completed',
                    'data' => $review360Val,
                ];
            };

            // ─── 10. Preload suggestedRating untuk semua formId ───────────────────
            // form_id sudah ada langsung di item (dari JOIN), tidak perlu lazy-load relation
            $cachedSuggestedRatings = [];
            foreach ($allFormIds as $formId) {
                // Cari item dengan form_id yang cocok langsung dari atribut JOIN
                $itemForForm = $withRequestsRaw->first(fn($item) => (string) $item->form_id === (string) $formId);
                if ($itemForForm) {
                    $empId = $itemForForm->employee->employee_id;
                    if (!isset($cachedSuggestedRatings[$empId][$formId])) {
                        $cachedSuggestedRatings[$empId][$formId] = $this->appService->suggestedRating($empId, $formId, $period);
                    }
                }
            }

            // ─── 11. Preload Calibration untuk withoutRequests (hanya cek exists) ──
            $calibrationExistsByEmployee = Calibration::where('approver_id', $id)
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('status', 'Pending')
                ->pluck('employee_id')
                ->flip(); // jadi Collection key => true untuk O(1) lookup

            // ─── 12. Group & map (tidak ada query di dalam loop) ──────────────────
            $datas = $allData->groupBy(function ($data) {
                return 'AllLevels';
            })->map(function ($group) use ($alaIdsWithRequests, $withRequestsRaw, $id) {
                // withRequests: filter $withRequestsRaw berdasarkan ALA.id yang ada di group
                $groupIds = $group->pluck('id')->flip(); // ALA.id dari $allData
                $withRequests = $withRequestsRaw
                    ->filter(fn($item) => $groupIds->has($item->id)) // item->id = ALA.id (fixed via selectRaw)
                    ->values();

                // withoutRequests: ALA item yang TIDAK punya approval_request
                // Gunakan $alaIdsWithRequests (pluck tanpa konflik id)
                $withoutRequests = $group
                    ->filter(fn($item) => !$alaIdsWithRequests->has($item->id))
                    ->values();

                return [
                    'with_requests' => $withRequests,
                    'without_requests' => $withoutRequests,
                ];
            })->sortKeys();

            Log::info('Grouped and processed data.', ['groupedDataCount' => $datas->count()]);

            // ─── 13. Process ratingDatas (semua data sudah dipreload, tidak ada query di loop) ──
            $ratingDatas = $datas->map(function ($group) use ($id, $period, $allCalibrations, $allRatingValues, $cachedSuggestedRatings, $convertRatingLocal, $ratingAllowedCheckLocal, $calibrationExistsByEmployee) {
                $withRequests = collect($group['with_requests'])->map(function ($data) use ($id, $period, $allCalibrations, $allRatingValues, $cachedSuggestedRatings, $convertRatingLocal, $ratingAllowedCheckLocal) {
                    $employeeId = $data->employee->employee_id;
                    // form_id langsung dari atribut JOIN, tidak perlu lazy-load approvalRequest relation
                    $formId = $data->form_id;

                    // Calibration data dari preload
                    $calibrationData = collect();
                    if ($formId && isset($allCalibrations[$employeeId][$formId])) {
                        $calibrationData = collect($allCalibrations[$employeeId][$formId]);
                    }

                    // Previous rating
                    $previousRating = $calibrationData->whereNotNull('rating')
                        ->where('approver_id', '!=', $id)
                        ->first();

                    // Suggested rating (dari cache, tidak query DB)
                    $suggestedRating = $cachedSuggestedRatings[$employeeId][$formId] ?? null;

                    $calibratorEntry = $calibrationData->where('approver_id', $id)->first();
                    $data->suggested_rating = $calibratorEntry
                        ? $convertRatingLocal($suggestedRating ?? 0, $calibratorEntry->id_calibration_group)
                        : null;

                    $firstCalibration = $calibrationData->first();
                    $data->previous_rating = $previousRating && $firstCalibration
                        ? $convertRatingLocal($previousRating->rating, $firstCalibration->id_calibration_group)
                        : null;
                    $data->previous_rating_name = $previousRating
                        ? $previousRating->approver->fullname . ' (' . $previousRating->approver->employee_id . ')'
                        : null;

                    // Rating value dari preload
                    $ratingRecord = $allRatingValues->get($employeeId);
                    $data->rating_value = $ratingRecord ? $ratingRecord->rating : null;

                    // Is calibrator
                    $data->is_calibrator = $calibrationData->where('approver_id', $id)
                        ->where('status', 'Pending')
                        ->isNotEmpty();

                    // Rating allowed (in-memory, tanpa query DB)
                    $data->rating_allowed = $ratingAllowedCheckLocal($employeeId);

                    // Rating incomplete & calibration
                    $data->rating_incomplete = $calibrationData->whereNull('rating')->whereNull('deleted_at')->count();
                    $data->calibrationData = $calibrationData;

                    // Rating status & approved date
                    $userCalibration = $calibrationData->first();
                    if ($userCalibration) {
                        $myCalibration = $calibrationData->where('approver_id', $id)->first();
                        $data->rating_status = $myCalibration ? $myCalibration->status : null;
                        $data->rating_approved_date = Carbon::parse($userCalibration->updated_at)->format('d M Y');
                    }

                    $data->onCalibratorPending = $calibrationData->where('approver_id', $id)->where('status', 'Pending')->count();

                    $pendingCalibrator = $calibrationData->where('status', 'Pending')->first();
                    $approvedCalibrator = $calibrationData->where('status', 'Approved')->first();

                    $data->current_calibrator = $pendingCalibrator && $pendingCalibrator->approver
                        ? $pendingCalibrator->approver->fullname . ' (' . $pendingCalibrator->approver->employee_id . ')'
                        : false;

                    $data->approver_name = $approvedCalibrator && $approvedCalibrator->approver
                        ? $approvedCalibrator->approver->fullname . ' (' . $approvedCalibrator->approver->employee_id . ')'
                        : (isset($data->status) && $data->status == 'Pending' && isset($data->approval_requests)
                            ? optional(optional($data->approval_requests)->approver)->fullname
                            : false);

                    return $data;
                });

                $withoutRequests = collect($group['without_requests'])->map(function ($data) use ($id, $ratingAllowedCheckLocal, $calibrationExistsByEmployee) {
                    $data->suggested_rating = null;
                    $data->is_calibrator = $calibrationExistsByEmployee->has($data->employee->employee_id);
                    $data->rating_allowed = $ratingAllowedCheckLocal($data->employee->employee_id);
                    return $data;
                });

                $combinedResults = $withRequests->merge($withoutRequests);
                Log::info('Group combined.', ['count' => $combinedResults->count()]);
                return $combinedResults;
            });

            Log::info('Processed all rating data.', ['ratingDatasCount' => $ratingDatas->count()]);

            // ─── 14. Calibration summary (tetap sama, hanya pakai $datas) ──────────
            $calibrations = $datas->map(function ($group) use ($calibrationDistribution, $id) {
                $calibratorPendingCount = collect($group['with_requests'])->where('onCalibratorPending', '>', 0)->count();

                $countWithRequests = count($group['with_requests']);
                $countWithoutRequests = count($group['without_requests']);
                $count = $countWithRequests + $countWithoutRequests;

                $ratingResults = [];
                $percentageResults = [];
                $calibrationArray = json_decode($calibrationDistribution, true);

                foreach ($calibrationArray as $key => $weight) {
                    $ratingResults[$key] = round($count * $weight);
                    $percentageResults[$key] = round(100 * $weight);
                }

                $totalRatingResults = array_sum($ratingResults);
                $difference = abs($count - $totalRatingResults);

                if ($difference !== 0) {
                    if ($totalRatingResults < $count) {
                        $totalWeight = array_sum($calibrationArray);
                        $normalizedWeights = array_map(fn($w) => $w / $totalWeight, $calibrationArray);
                        foreach ($normalizedWeights as $key => $normalizedWeight) {
                            $ratingResults[$key] += floor($difference * $normalizedWeight);
                        }
                        $newTotal = array_sum($ratingResults);
                        if ($newTotal !== $count) {
                            $maxWeightKey = array_keys($calibrationArray, max($calibrationArray))[0];
                            $ratingResults[$maxWeightKey] += ($count - $newTotal);
                        }
                    } elseif ($totalRatingResults > $count) {
                        while ($difference > 0) {
                            $lowestKey = collect($percentageResults)
                                ->filter(fn($percentage, $key) => $ratingResults[$key] >= 1)
                                ->sort()->keys()->first();
                            if ($lowestKey !== null) {
                                $ratingResults[$lowestKey]--;
                                $difference--;
                            } else {
                                break;
                            }
                        }
                    }
                }

                $suggestedRatingCounts = collect($group['with_requests'])->pluck('suggested_rating')->countBy();
                $totalSuggestedRatings = $suggestedRatingCounts->sum();

                $combinedResults = [];
                foreach ($calibrationArray as $key => $weight) {
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

            $activeLevel = null;
            foreach ($calibrations as $level => $data) {
                if (!empty($data)) {
                    $activeLevel = $level;
                    break;
                }
            }

            $parentLink = 'Calibration';
            $link = 'Rating';
            $id_calibration_group = 'c7b602c2-1791-4552-81e4-87525f8b0d83';

            Log::info('Returning view.', ['activeLevel' => $activeLevel]);

            // dd($ratingDatas);

            return view('pages.rating.app', compact(
                'ratingDatas',
                'calibrations',
                'masterRating',
                'link',
                'parentLink',
                'activeLevel',
                'id_calibration_group'
            ));

        } catch (Exception $e) {
            Log::error('Error in rating onbehalf method: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->route('onbehalf');
        }
    }

}
