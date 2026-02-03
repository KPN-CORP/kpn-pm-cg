<?php

namespace App\Http\Controllers;

use App\Exports\UserExport;
use App\Models\Achievements;
use App\Models\Appraisal;
use App\Models\AppraisalContributor;
use App\Models\ApprovalLayerAppraisal;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSnapshots;
use App\Models\EmployeeAppraisal;
use App\Models\FormAppraisal;
use App\Models\FormGroupAppraisal;
use App\Models\Goal;
use App\Models\KpiCompany;
use App\Models\MasterWeightage;
use App\Models\User;
use Carbon\Carbon;
use Exception;
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
use App\Services\AppService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MyAppraisalController extends Controller
{
    
    protected $category;
    protected $user;
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->user = Auth::user()->employee_id;
        $this->appService = $appService;
        $this->category = 'Appraisal';
    }

    function formatDate($date)
    {
        // Parse the date using Carbon
        $carbonDate = Carbon::parse($date);

        // Check if the date is today
        if ($carbonDate->isToday()) {
            return 'Today ' . $carbonDate->format('ga');
        } else {
            return $carbonDate->format('d M ga');
        }
    }

    public function index(Request $request) {
        
        $user = $this->user;
        $period = $this->appService->appraisalPeriod();
        $filterYear = $request->input('filterYear');
        $accessMenu = [];

        $employee = EmployeeAppraisal::where('employee_id', $user)->first();
        if ($employee) {
            $accessMenu = json_decode($employee->access_menu, true);
        }

        try {

            // Retrieve approval requests
            $datasQuery = ApprovalRequest::with([
                'employee', 'appraisal.goal', 'appraisal.approvalSnapshots' => function ($query) {
                    $query->where('created_by', Auth::user()->id);
                }, 'updatedBy', 'adjustedBy', 'initiated', 'manager', 'contributor',
                'approval' => function ($query) {
                    $query->with('approverName');
                }
            ])
            ->whereHas('approvalLayerAppraisal', function ($query) use ($user) {
                $query->where('employee_id', $user)->orWhere('approver_id', $user);
            })
            ->where('employee_id', $user)->where('category', $this->category);

            if (!empty($filterYear)) {
                $datasQuery->where('period', $filterYear);
            }

            $datas = $datasQuery->get();
                        
            $formattedData = $datas->map(function($item) {
                $item->formatted_created_at = $this->appService->formatDate($item->appraisal->created_at);

                $item->formatted_updated_at = $this->appService->formatDate($item->appraisal->updated_at);

                if ($item->sendback_to == $item->employee->employee_id) {
                    $item->name = $item->employee->fullname . ' (' . $item->employee->employee_id . ')';
                    $item->approvalLayer = '';
                } else {
                    $item->name = $item->manager ? $item->manager->fullname . ' (' . $item->manager->employee_id . ')' : '';
                    $item->approvalLayer = ApprovalLayerAppraisal::where('employee_id', $item->employee_id)
                                                        ->where('approver_id', $item->current_approval_id)
                                                        ->value('layer');
                }
                return $item;
            });
                        
            $adjustByManager = $datas->first()->updatedBy ? 
                ApprovalLayerAppraisal::where('approver_id', $datas->first()->updatedBy->employee_id)
                    ->where('employee_id', $datas->first()->employee_id)
                    ->first() : null;

            // Setelah data digabungkan, gunakan combineFormData untuk setiap jenis kontributor

            $data = [];
            foreach ($formattedData as $request) {
                $formGroupData = $this->appService->formGroupAppraisal($user, 'Appraisal Form', $request->period);
                $cultureData = $this->getDataByName($formGroupData['data']['form_appraisals'], 'Culture') ?? [];
                $leadershipData = $this->getDataByName($formGroupData['data']['form_appraisals'], 'Leadership') ?? [];
                $technicalData = $this->getDataByName($formGroupData['data']['form_appraisals'], 'Technical') ?? [];
                $sigapData = $this->getDataByName($formGroupData['data']['form_appraisals'], 'Sigap') ?? [];
                
                if ($request->appraisal->goal->form_status != 'Draft' || $request->created_by == Auth::user()->id) {
                    
                    $goalData = $request ? json_decode($request->appraisal->goal->form_data, true) : [];
                    
                    $form_data = Auth::user()->id == $request->appraisal->created_by
                    ? $request->appraisal->approvalSnapshots->form_data
                    : $request->appraisal->form_data;
                    
                    $appraisalData = json_decode($form_data, true) ?? [];
                    
                    $groupedContributors = $request->contributor->groupBy('contributor_type');
                    
                    $employeeData = $request->employee;
                    
                    $formData = $this->appService->combineFormData($appraisalData, $goalData, 'employee', $employeeData, $request->period);
                    
                    if (isset($formData['totalKpiScore'])) {
                        $appraisalData['kpiScore'] = round($formData['totalKpiScore'], 2);
                        $appraisalData['cultureScore'] = round($formData['totalCultureScore'], 2);
                        $appraisalData['leadershipScore'] = round($formData['totalLeadershipScore'], 2);
                        $appraisalData['technicalScore'] = round($formData['totalTechnicalScore'], 2);
                        $appraisalData['sigapScore'] = round($formData['totalSigapScore'], 2);
                    }
                    
                    foreach ($formData['formData'] as &$form) {
                        if ($form['formName'] === 'Leadership') {
                            foreach ($leadershipData as $index => $leadershipItem) {
                                foreach ($leadershipItem['items'] as $itemIndex => $item) {
                                    if (isset($form[$index][$itemIndex])) {
                                        $form[$index][$itemIndex] = [
                                            'formItem' => $item,
                                            'score' => $form[$index][$itemIndex]['score']
                                        ];
                                    }
                                }
                                $form[$index]['title'] = $leadershipItem['title'];
                            }
                        }
                        if ($form['formName'] === 'Technical') {
                            foreach ($technicalData as $index => $technicalItem) {
                                foreach ($technicalItem['items'] as $itemIndex => $item) {
                                    if (isset($form[$index][$itemIndex])) {
                                        $form[$index][$itemIndex] = [
                                            'formItem' => $item,
                                            'score' => $form[$index][$itemIndex]['score']
                                        ];
                                    }
                                }
                                $form[$index]['title'] = $technicalItem['title'];
                            }
                        }
                        if ($form['formName'] === 'Culture') {
                            foreach ($cultureData as $index => $cultureItem) {
                                foreach ($cultureItem['items'] as $itemIndex => $item) {
                                    if (isset($form[$index][$itemIndex])) {
                                        $form[$index][$itemIndex] = [
                                            'formItem' => $item,
                                            'score' => $form[$index][$itemIndex]['score']
                                        ];
                                    }
                                }
                                $form[$index]['title'] = $cultureItem['title'];
                            }
                        }
                        if ($form['formName'] === 'Sigap') {
                            foreach ($sigapData as $index => $sigapItem) {
                                foreach ($sigapItem['items'] as $itemIndex => $item) {
                                    if (isset($form[$index][$itemIndex])) {
                                        $form[$index][$itemIndex] = [
                                            'formItem' => $item,
                                            'score' => $form[$index][$itemIndex]['score']
                                        ];
                                    }
                                }
                                $form[$index]['title'] = $sigapItem['title'];
                                $form[$index]['items'] = $sigapItem['items'];
                            }
                        }
                        if ($form['formName'] === 'Technical') {
                            foreach ($technicalData as $index => $technicalItem) {
                                foreach ($technicalItem['items'] as $itemIndex => $item) {
                                    if (isset($form[$index][$itemIndex])) {
                                        $form[$index][$itemIndex] = [
                                            'formItem' => $item,
                                            'score' => $form[$index][$itemIndex]['score']
                                        ];
                                    }
                                }
                                $form[$index]['title'] = $technicalItem['title'];
                            }
                        }
                    }
                    

                    $formGroup = FormGroupAppraisal::with('rating')->find($request->appraisal->form_group_id);

                    $finalRating = null;

                    foreach ($formGroup->rating as $rating) {
                        if ((int)$rating->value === (int)$request->appraisal->rating) {
                            $finalRating = $rating->parameter; // Get the matching parameter
                            break; // Stop looping once a match is found
                        }
                    }

                    $dataApprover = $request->approval->first() ? $request->approval->first()->approverName->fullname : '';

                    $dataItem = new stdClass();
                    $dataItem->request = $request;
                    $dataItem->approver_name = $dataApprover;
                    $dataItem->name = $request->name;
                    $dataItem->approvalLayer = $request->approvalLayer;
                    $dataItem->finalRating = $finalRating;
                    $dataItem->formData = $formData;
                    $dataItem->appraisalData = $appraisalData;
                    $data[] = $dataItem;
                }
            }

            $mergedResults = [];

            // Kumpulan data berdasarkan contributor_type
            $contributorManagerContent = [];
            $combinedPeersData = [];
            $combinedSubData = [];

            // Gabungkan formData untuk setiap contributor_type
            foreach ($groupedContributors as $type => $contributors) {
                // Siapkan array untuk menampung formData dari kontributor dalam grup
                $formDataSets = [];

                foreach ($contributors as $contributor) {
                    // Decode form_data JSON dari setiap kontributor
                    $formData = json_decode($contributor->form_data, true);

                    // Kumpulkan formData untuk setiap kontributor
                    $formDataSets[] = $formData;
                }

                // Gabungkan semua formData menggunakan fungsi mergeFormData
                $mergedFormData = $this->mergeFormData($formDataSets);

                // Simpan hasil gabungan sesuai dengan contributor_type
                if ($type === 'manager') {
                    $contributorManagerContent = $mergedFormData;
                } elseif ($type === 'peers') {
                    $combinedPeersData = $mergedFormData;
                } elseif ($type === 'subordinate') {
                    $combinedSubData = $mergedFormData;
                }
            }
            
            $path = base_path('resources/goal.json');
            if (!File::exists($path)) {
                $options = ['UoM' => [], 'Type' => []];
            } else {
                $options = json_decode(File::get($path), true);
            }

            $uomOption = $options['UoM'] ?? [];
            $typeOption = $options['Type'] ?? [];

            $parentLink = __('Appraisal');
            $link = __('My Appraisal');

            $selectYear = ApprovalRequest::where('employee_id', $datas->first()->employee_id)->where('category', $this->category)->select('period')->distinct()
                ->orderBy('period', 'desc')->get();

            $achievements = Achievements::where('employee_id', $user)->where('period', $period)->get();

            // View Cement only //
            $viewAchievement = $employeeData->group_company == 'Cement' ? true : false;

            return view('pages.appraisals.my-appraisal', compact('data', 'link', 'parentLink', 'uomOption', 'typeOption', 'accessMenu', 'selectYear', 'adjustByManager', 'achievements', 'viewAchievement'));

        } catch (Exception $e) {
            Log::error('Error in index method: ' . $e->getMessage());

            // Return empty data to be consumed in the Blade template
            return view('pages.appraisals.my-appraisal', [
                'data' => [],
                'link' => 'My Appraisal',
                'parentLink' => 'Appraisal',
                'formData' => ['formData' => []],
                'uomOption' => [],
                'typeOption' => [],
                'goals' => null,
                'selectYear' => [],
                'adjustByManager' => null,
                'appraisalData' => [],
                'accessMenu' => $accessMenu,
                'achievements' => [],
            ]);
        }
    }

    public function create(Request $request)
    {
        try {
            $step = $request->input('step', 1);

            $period = $this->appService->appraisalPeriod();

            $kpiCompanies = KpiCompany::where('employee_id', $request->id)
                ->where('period', $period)
                ->first();

            $goalChecked = Goal::where('employee_id', $request->id)->where('period', $period)->exists();

            $goal = Goal::where('employee_id', $request->id)->where('period', $period)->first();

            $appraisal = Appraisal::where('employee_id', $request->id)->where('period', $period)->first();

            $accessMenu = [];

            $employee = EmployeeAppraisal::where('employee_id', $request->id)->first();
            if ($employee) {
                $accessMenu = json_decode($employee->access_menu, true);
            }

            // check goals
            if ($goalChecked) {

                // decode form_data
                $goalData = json_decode($goal->form_data, true);

                // VALIDASI kpiCompanies
                if (
                    empty($kpiCompanies) ||
                    !isset($kpiCompanies['form_data']) ||
                    empty($kpiCompanies['form_data'])
                ) {
                    // jika KPI tidak ada, set actual = null semua
                    foreach ($goalData as &$goalItem) {
                        $goalItem['actual'] = null;
                    }
                    unset($goalItem);
                } else {
                
                    // decode form_data KPI
                    $kpiData = is_string($kpiCompanies['form_data'])
                        ? json_decode($kpiCompanies['form_data'], true)
                        : $kpiCompanies['form_data'];
                
                    // pastikan hasil decode array
                    if (!is_array($kpiData)) {
                        foreach ($goalData as &$goalItem) {
                            $goalItem['actual'] = null;
                        }
                        unset($goalItem);
                    } else {
                
                        // mapping berdasarkan index (asumsi urutan sama)
                        foreach ($goalData as $index => &$goalItem) {
                            $goalItem['actual'] = $kpiData[$index]['achievement'] ?? null;
                        }
                        unset($goalItem);
                    }
                }
                
                // simpan kembali ke model
                $goal->form_data = json_encode($goalData);


            } else {
                Session::flash('error', "Your Goal for $period are not found. Please create your Goal first.");
                return redirect()->route('appraisals');
            }

            if (!$accessMenu['createpa'] && !$accessMenu['accesspa']) {
                Session::flash('error', "You are not eligible to create Appraisal $period.");
                return redirect()->route('appraisals');
            }

            // check appraisals
            if ($appraisal) {
                Session::flash('error', "Appraisal $period already initiated.");
                return redirect()->route('appraisals');
            }
            
            $approval = ApprovalLayerAppraisal::select('approver_id')->where('employee_id', $request->id)->where('layer_type', 'manager')->where('layer', 1)->first();
      
            if (!$approval) {
                Session::flash('error', "No Reviewer assigned, please contact admin to assign reviewer");
                return redirect()->back();
            }
            
            $calibrator = ApprovalLayerAppraisal::where('layer', 1)->where('layer_type', 'calibrator')->where('employee_id', $request->id)->value('approver_id');

            if (!$calibrator) {
                Session::flash('error', "No Layer assigned, please contact admin to assign layer");
                return redirect()->back();
            }
            
            
            // Get form group appraisal
            $formGroupData = $this->appService->formGroupAppraisal($request->id, 'Appraisal Form', $period);
            
            // Validate formGroupData is not empty
            if (empty($formGroupData) || !isset($formGroupData['data']) || empty($formGroupData['data']['form_appraisals'])) {
                throw new Exception("Form group configuration is incomplete or missing.");
            }
                    
            $formTypes = $formGroupData['data']['form_names'] ?? [];
            $formDatas = $formGroupData['data']['form_appraisals'] ?? [];
                    
            $filteredFormData = array_filter($formDatas, function($form) use ($formTypes) {
                return in_array($form['name'], $formTypes);
            });

            $ratings = $formGroupData['data']['rating'] ?? [];

            $achievements = Achievements::where('employee_id', $request->id)->where('period', $period)->get();

            $parentLink = __('Appraisal');
            $link = 'Initiate Appraisal';

            // View Cement only //
            $viewAchievement = $employee->group_company == 'Cement' ? true : false;

            // Pass the data to the view
            return view('pages/appraisals/create', compact('step', 'parentLink', 'link', 'filteredFormData', 'formGroupData', 'goalData', 'goal', 'approval', 'ratings', 'appraisal', 'achievements', 'viewAchievement'));
        } catch (Exception $e) {
            Log::error('Error in create method: ' . $e->getMessage());
            Session::flash('error', 'Failed to load appraisal form: ' . $e->getMessage());
            return redirect()->route('appraisals');
        }
    }

    public function store(Request $request)
    {
        try {

            // Log::info('Store started');
            $submit_status = $request->submit_type == 'submit_draft' ? 'Draft' : 'Submitted';
            $messages = $request->submit_type == 'submit_draft' ? 'Draft saved successfully.' : 'Appraisal submitted successfully.';
            $period = $this->appService->appraisalPeriod();

            // Validasi data
            $validatedData = $request->validate([
                'form_group_id' => 'required|string',
                'employee_id'   => 'required|string|size:11',
                'approver_id'   => 'required|string|size:11',
                'formGroupName' => 'required|string|min:5|max:100',
                'formData'      => 'required|array',
                'attachment'    => 'nullable|array',
                'attachment.*'  => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png|max:10240',
            ]);

            $formGroupName = $validatedData['formGroupName'];
            $formData = $validatedData['formData'];

            $goals = Goal::with(['employee'])
                ->where('employee_id', $validatedData['employee_id'])
                ->where('period', $period)
                ->first();

            if (!$goals) {
                return back()->withErrors(['message' => 'Goal not found for selected employee and period.']);
            }

            $datas = [
                'formGroupName' => $formGroupName,
                'formData' => $formData,
            ];

            $contributorCheck = AppraisalContributor::select('appraisal_id')
                ->where('employee_id', $validatedData['employee_id'])
                ->where('period', $period)
                ->first();

            $employeeId = $validatedData['employee_id'];
            $timestamp  = Carbon::now()->format('His');
            $baseDir    = 'files/docs_pa';                       // di disk 'public'
            Storage::disk('public')->makeDirectory($baseDir);       // idempotent

            $paths = [];
            $files = $request->file('attachment', []);

            foreach ($files as $i => $file) {
                if (!($file instanceof UploadedFile)) continue;
                $origName = $file->getClientOriginalName();
                $baseName = pathinfo($origName, PATHINFO_FILENAME);
                $clean = preg_replace('/[^\pL0-9 _.-]+/u', '', $baseName); // buang char aneh
                $clean = trim(preg_replace('/\s+/', ' ', $clean));         // rapikan spasi
                $clean = str_replace(' ', '_', mb_substr($clean, 0, 80));  // ganti spasi -> underscore
                $ext      = strtolower($file->getClientOriginalExtension() ?: $file->extension());
                $safeExt  = $ext ?: 'bin';
                $filename = "{$clean}_{$period}_{$timestamp}_" . str_pad($i+1, 2, '0', STR_PAD_LEFT) . ".{$safeExt}";

                Storage::disk('public')->putFileAs($baseDir, $file, $filename);

                // Simpan path web (akses via /storage)
                $paths[] = "storage/{$baseDir}/{$filename}";
            }
            // Log::info('Validated data', $request->all());
            Log::info('Appraisal Data', [
                    'employee_id' => $validatedData['employee_id'],
                    'form_group_id' => $validatedData['form_group_id'],
                    'data' => json_encode($datas),
                    'period' => $period,
                ]);
            // Simpan appraisal
            $appraisal = new Appraisal;
            $appraisal->id = $contributorCheck->appraisal_id ?? Str::uuid();
            $appraisal->goals_id = $goals->id;
            $appraisal->employee_id = $validatedData['employee_id'];
            $appraisal->form_group_id = $validatedData['form_group_id'];
            $appraisal->category = $this->category;
            $appraisal->form_data = json_encode($datas);
            $appraisal->form_status = $submit_status;
            $appraisal->period = $period;
            $appraisal->created_by = Auth::user()->id;
            $appraisal->file = empty($paths) ? null : json_encode($paths);
            $appraisal->save();

            // Simpan snapshot
            $snapshot = new ApprovalSnapshots;
            $snapshot->id = Str::uuid();
            $snapshot->form_id = $appraisal->id;
            $snapshot->form_data = json_encode($datas);
            $snapshot->employee_id = $validatedData['employee_id'];
            $snapshot->created_by = Auth::user()->id;
            $snapshot->save();

            // Simpan approval request
            $approval = new ApprovalRequest();
            $approval->form_id = $appraisal->id;
            $approval->category = $this->category;
            $approval->period = $period;
            $approval->employee_id = $validatedData['employee_id'];
            $approval->current_approval_id = $validatedData['approver_id'];
            $approval->created_by = Auth::user()->id;
            $approval->save();

            // Log::info('Appraisal saved successfully, redirecting...');
            // return redirect('appraisals')->with('success', $messages);

        } catch (ValidationException $e) {
            // Kembalikan ke form dengan error validasi
            // Log::error('Validation error', ['errors' => $e->validator->errors()]);
            return back()->withErrors($e->validator)->withInput();
        } catch (\Exception $e) {
            // Tangani error umum lainnya
            // Log::error('General error', ['message' => $e->getMessage()]);
            return back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage())->withInput();
        }
    }

    private function getDataByName($data, $name) {
        foreach ($data as $item) {
            if ($item['name'] === $name) {
                return $item['data'];
            }
        }
        return null;
    }

    private function mergeFormData(array $formDataSets)
    {
        $mergedData = [];

        foreach ($formDataSets as $formData) {
            foreach ($formData['formData'] as $form) {

                $formName = $form['formName'];

                // Check if formName already exists in the merged data
                $existingFormIndex = collect($mergedData)->search(function ($item) use ($formName) {
                    return $item['formName'] === $formName;
                });

                if ($existingFormIndex !== false) {
                    // Merge scores for the existing form
                    foreach ($form as $key => $scores) {
                        if (is_numeric($key)) {
                            $mergedData[$existingFormIndex][$key] = array_merge($mergedData[$existingFormIndex][$key] ?? [], $scores);
                        }
                    }
                } else {
                    // Add the form to the merged data
                    $mergedData[] = $form;
                }
            }
        }

        return [
            'formGroupName' => 'Appraisal Form',
            'formData' => $mergedData
        ];

    }

    function show($id) {
        $data = Goal::find($id);
        
        return view('pages.goals.modal', compact('data')); //modal body hilang ketika modal show bentrok dengan view goal
    }
    

    function edit(Request $request) {
        try {
            $step = $request->input('step', 1);

        $period = $this->appService->appraisalPeriod();

        $appraisal = Appraisal::with(['approvalRequest'])->where('id', $request->id)->where('period', $period)->first();

        $parentLink = __('Appraisal');
        $link = __('Edit');

        if(!$appraisal){
            return redirect()->route('appraisals');
        }else{
            $goal = Goal::where('employee_id', $appraisal->employee_id)->where('period', $period)->first();

            $goalData = json_decode($goal->form_data, true);

            $approvalRequest = ApprovalRequest::where('form_id', $appraisal->id)->first();

            // Read the content of the JSON files
            $formGroupContent = $this->appService->formGroupAppraisal($appraisal->employee_id, 'Appraisal Form', $period);
            
            if (!$formGroupContent || empty($formGroupContent['data']['form_appraisals'])) {
                throw new Exception("Form group configuration is incomplete or missing for employee.");
            }
            
            $formGroupData = $formGroupContent;
            
            $formTypes = $formGroupData['data']['form_names'] ?? [];
            $formDatas = $formGroupData['data']['form_appraisals'] ?? [];
            
            
            $filteredFormData = array_filter($formDatas, function($form) use ($formTypes) {
                return in_array($form['name'], $formTypes);
            });
            
            $ratings = $formGroupData['data']['rating'] ?? [];
            
            $approval = ApprovalLayerAppraisal::select('approver_id')->where('employee_id', $appraisal->employee_id)->where('layer', 1)->first();
            
            $cultureData = $this->getDataByName($formGroupData['data']['form_appraisals'], 'Culture') ?? [];
            $leadershipData = $this->getDataByName($formGroupData['data']['form_appraisals'], 'Leadership') ?? [];
            $technicalData = $this->getDataByName($formGroupData['data']['form_appraisals'], 'Technical') ?? [];
            $sigapData = $this->getDataByName($formGroupData['data']['form_appraisals'], 'Sigap') ?? [];
            
            // Read the contents of the JSON file
            $formData = json_decode($appraisal->form_data, true);
            
            $formCount = count($formData);
            
            $data = json_decode($appraisal->form_data, true);

            
            $selfReviewData = [];
            foreach ($formData['formData'] as $item) {
                if ($item['formName'] === 'KPI') {
                    $selfReviewData = array_slice($item, 1);
                    break;
                }
            }

            
            // Add the achievements to the goalData
            foreach ($goalData as $index => &$goal) {
                if (isset($selfReviewData[$index])) {
                    $goal['actual'] = $selfReviewData[$index]['achievement'];
                } else {
                    $goal['actual'] = [];
                }
            }

            foreach ($formData['formData'] as &$form) {                
                if ($form['formName'] === 'Culture') {
                    foreach ($form as $key => &$value) {
                        if (is_numeric($key)) {
                            $scores = [];
                            foreach ($value as $score) {
                                $scores[] = $score['score'];
                            }
                            $value = ['score' => $scores];
                        }
                    }
                }
                if ($form['formName'] === 'Leadership') {
                    foreach ($form as $key => &$value) {
                        if (is_numeric($key)) {
                            $scores = [];
                            foreach ($value as $score) {
                                $scores[] = $score['score'];
                            }
                            $value = ['score' => $scores];
                        }
                    }
                }
                if ($form['formName'] === 'Technical') {
                    foreach ($form as $key => &$value) {
                        if (is_numeric($key)) {
                            $scores = [];
                            foreach ($value as $score) {
                                $scores[] = $score['score'];
                            }
                            $value = ['score' => $scores];
                        }
                    }
                }
                if ($form['formName'] === 'Sigap') {

                    foreach ($form as $key => $value) {
                        if (is_numeric($key)) {
                            $scores = [];
                            foreach ($value as $score) {
                                $scores[] = $score;
                            }
                            $value = $scores;
                        }

                    }

                }

            }

            // Function to merge scores
            function mergeScores($formData, $filteredFormData) {
                foreach ($formData['formData'] as $formData) {
                    $formName = $formData['formName'];
                    foreach ($filteredFormData as &$section) {
                        if ($section['name'] === $formName) {
                            foreach ($formData as $key => $value) {
                                if (is_numeric($key)) {
                                    if (isset($value['score'])) {
                                        if (is_array($value['score'])) {
                                            foreach ($value['score'] as $scoreIndex => $scoreValue) {
                                                if (isset($section['data'][$key]['score'][$scoreIndex])) {
                                                    $section['data'][$key]['score'][$scoreIndex] += $scoreValue;
                                                } else {
                                                    $section['data'][$key]['score'][$scoreIndex] = $scoreValue;
                                                }
                                            }
                                        } else {
                                            // Handle single score value (for Sigap or other forms)
                                            $section['data'][$key]['score'] = $value['score'] ?? null;

                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                return $filteredFormData;
            }
            
            // Merge the scores
            $filteredFormData = mergeScores($formData, $filteredFormData);

            // Process form data for display
            foreach ($filteredFormData as &$form) {
                if ($form['name'] === 'Leadership') {
                    foreach ($leadershipData as $index => $leadershipItem) {
                        foreach ($leadershipItem['items'] as $itemIndex => $item) {
                            if (isset($form['data'][$index]['score'][$itemIndex])) {
                                $form['data'][$index]['items'][$itemIndex]['formItem'] = $item;
                                $form['data'][$index]['items'][$itemIndex]['score'] = $form['data'][$index]['score'][$itemIndex];
                            }
                        }
                        $form['data'][$index]['title'] = $leadershipItem['title'];
                    }
                }
                if ($form['name'] === 'Technical') {
                    foreach ($technicalData as $index => $technicalItem) {
                        foreach ($technicalItem['items'] as $itemIndex => $item) {
                            if (isset($form['data'][$index]['score'][$itemIndex])) {
                                $form['data'][$index]['items'][$itemIndex]['formItem'] = $item;
                                $form['data'][$index]['items'][$itemIndex]['score'] = $form['data'][$index]['score'][$itemIndex];
                            }
                        }
                        $form['data'][$index]['title'] = $technicalItem['title'];
                    }
                }
                if ($form['name'] === 'Culture') {
                    foreach ($cultureData as $index => $cultureItem) {
                        foreach ($cultureItem['items'] as $itemIndex => $item) {
                            if (isset($form['data'][$index]['score'][$itemIndex])) {
                                $form['data'][$index]['items'][$itemIndex]['formItem'] = $item;
                                $form['data'][$index]['items'][$itemIndex]['score'] = $form['data'][$index]['score'][$itemIndex];
                            }
                        }
                        $form['data'][$index]['title'] = $cultureItem['title'];
                    }
                }
                if ($form['name'] === 'Sigap') {
                    foreach ($sigapData as $index => $sigapItem) {
                        foreach ($sigapItem['items'] as $itemIndex => $item) {
                            if (isset($form['data'][$index]['score'][$itemIndex])) {
                                $form['data'][$index]['items'][$itemIndex]['formItem'] = $item;
                                $form['data'][$index]['items'][$itemIndex]['score'] = $form['data'][$index]['score'][$itemIndex];
                            }
                        }
                        $form['data'][$index]['title'] = $sigapItem['title'];
                    }
                }
            }

            $achievements = Achievements::where('employee_id', $appraisal->employee_id)->where('period', $period)->get();

            $employee = EmployeeAppraisal::where('employee_id', $this->user)->first();

            // View Cement only //
            $viewAchievement = $employee->group_company == 'Cement' ? true : false;
            
            $sefInitiate = $appraisal->created_by == Auth::id();
            
            $viewCategory = 'Edit';
            

            return view('pages.appraisals.edit', compact('step', 'goal', 'appraisal', 'goalData', 'formCount', 'filteredFormData', 'link', 'data', 'approvalRequest', 'parentLink', 'approval', 'formGroupData', 'ratings', 'viewCategory', 'achievements', 'viewAchievement', 'sefInitiate'));
        }
        } catch (Exception $e) {
            Log::error('Error in edit method: ' . $e->getMessage());
            Session::flash('error', 'Failed to load appraisal form: ' . $e->getMessage());
            return redirect()->route('appraisals');
        }
    }

    public function update(Request $request)
    {
        $submitStatus = $request->submit_type === 'submit_draft' ? 'Draft' : 'Submitted';
        $message      = $request->submit_type === 'submit_draft'
            ? 'Draft saved successfully.'
            : 'Appraisal updated successfully.';
        $period = $this->appService->appraisalPeriod();

        // ✅ Validasi
        $validated = $request->validate([
            'id'            => 'required|uuid',
            'employee_id'   => 'required|string|size:11',
            'formGroupName' => 'required|string|min:5|max:100',
            'formData'      => 'required|array',
            // multi-file (baru)
            'attachment'    => 'nullable|array',
            'attachment.*'  => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png|max:10240', // 10MB per file (server-side); total dibatasi manual di bawah
            // daftar file lama yang dipertahankan
            'keep_files'    => 'nullable|array',
            'keep_files.*'  => 'string',
        ]);

        // Normalisasi formData (sesuai logic kamu)
        $formData = $validated['formData'];
        foreach ($formData as &$form) {
            if (($form['formName'] ?? null) === 'KPI') {
                foreach ($form as $key => &$value) {
                    if (is_array($value) && isset($value['achievement'])) {
                        // TODO: isi $value['score'] jika ada perhitungan server-side
                        // $value['score'] = ...;
                    }
                }
            }
            if (in_array(($form['formName'] ?? null), ['Culture','Leadership'], true)) {
                foreach ($form as $key => &$value) {
                    if (is_numeric($key)) {
                        $scores = [];
                        foreach ($value as $score) {
                            $scores[] = ['score' => $score['score']];
                        }
                        $value = $scores;
                    }
                }
            }
        }
        unset($form); // keluar dari reference

        $appraisal = Appraisal::findOrFail($validated['id']);

        $dataPayload = [
            'formGroupName' => $validated['formGroupName'],
            'formData'      => $formData,
        ];

        // ===== File handling (multi-file) =====
        $baseDir   = 'files/docs_pa';             // disk 'public'
        Storage::disk('public')->makeDirectory($baseDir);

        // Ambil list file lama dari DB (bisa JSON array atau string)
        $existingRaw  = $appraisal->file;
        $existingList = is_array($existingRaw) ? $existingRaw : (json_decode($existingRaw, true) ?: ($existingRaw ? [$existingRaw] : []));

        // File lama yang user putuskan untuk dipertahankan
        $kept = $validated['keep_files'] ?? [];      // bentuk "storage/files/appraisals/xxx.ext"
        $kept = array_values(array_filter($kept, fn($p) => is_string($p) && $p !== ''));

        // Hapus fisik file yang TIDAK di-keep
        $toDelete = array_values(array_diff($existingList, $kept));
        foreach ($toDelete as $webPath) {
            $diskPath = Str::after($webPath, 'storage/'); // "files/appraisals/xxx.ext"
            if ($diskPath && Storage::disk('public')->exists($diskPath)) {
                Storage::disk('public')->delete($diskPath);
            }
        }

        // File baru dari request (normalisasi single/multi/null)
        $filesInput = $request->file('attachment');
        $newFiles   = [];
        if ($filesInput instanceof UploadedFile) {
            $newFiles = [$filesInput];
        } elseif (is_array($filesInput)) {
            $newFiles = array_values(array_filter($filesInput, fn($f) => $f instanceof UploadedFile));
        }

        // (Opsional tapi disarankan) Validasi TOTAL 10MB (kept + new)
        $totalBytesKept = array_sum(array_map(function ($webPath) {
            $diskPath = Str::after($webPath, 'storage/');
            return Storage::disk('public')->exists($diskPath) ? Storage::disk('public')->size($diskPath) : 0;
        }, $kept));
        $totalBytesNew = array_sum(array_map(fn(UploadedFile $f) => $f->getSize(), $newFiles));
        if (($totalBytesKept + $totalBytesNew) > 10 * 1024 * 1024) {
            return back()->withErrors(['attachment' => 'Total file size exceeds 10MB.'])->withInput();
        }

        // Simpan file baru
        $employeeId = $validated['employee_id'];
        $timestamp  = Carbon::now()->format('His');

        // Agar penamaan urut _01, _02, ... dimulai setelah jumlah kept
        $startIdx = count($kept);
        $savedPaths = [];
        foreach ($newFiles as $i => $file) {
            $origName = $file->getClientOriginalName();
            $baseName = pathinfo($origName, PATHINFO_FILENAME);
            $clean = preg_replace('/[^\pL0-9 _.-]+/u', '', $baseName); // buang char aneh
            $clean = trim(preg_replace('/\s+/', ' ', $clean));         // rapikan spasi
            $clean = str_replace(' ', '_', mb_substr($clean, 0, 80));  // ganti spasi -> underscore
            $ext = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
            $seq = str_pad($startIdx + $i + 1, 2, '0', STR_PAD_LEFT);
            $filename = "{$clean}_{$period}_{$timestamp}_{$seq}.{$ext}";
            Storage::disk('public')->putFileAs($baseDir, $file, $filename);
            $savedPaths[] = "storage/{$baseDir}/{$filename}"; // path web (untuk asset())
        }

        // Gabungkan: kept + new
        $finalPaths = array_values(array_merge($kept, $savedPaths));

        DB::beginTransaction();
        try {
            // Simpan appraisal
            $appraisal->form_data   = json_encode($dataPayload);
            $appraisal->form_status = $submitStatus;
            $appraisal->updated_by  = Auth::id();
            $appraisal->file        = empty($finalPaths) ? null : json_encode($finalPaths); // simpan selalu sebagai JSON array atau null
            $appraisal->save();

            // Update snapshot
            $snapshot = ApprovalSnapshots::where('form_id', $appraisal->id)
                        ->where('employee_id', $appraisal->employee_id)
                        ->where('created_by', Auth::id())
                        ->first();
            if ($snapshot) {
                $snapshot->form_data  = json_encode($dataPayload);
                $snapshot->updated_by = Auth::id();
                $snapshot->save();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            // (opsional) rollback file baru yang sudah terupload
            foreach ($savedPaths as $webPath) {
                $diskPath = Str::after($webPath, 'storage/');
                if ($diskPath && Storage::disk('public')->exists($diskPath)) {
                    Storage::disk('public')->delete($diskPath);
                }
            }
            report($e);
            return back()->withErrors(['message' => 'Failed to update appraisal.'])->withInput();
        }

        return redirect('appraisals')->with('success', $message);
    }
    
    public function destroyFile(Request $request)
    {
        $request->validate([
            'appraisal_id' => 'required|uuid',
            'path'         => 'required|string', // e.g. "storage/files/docs_pa/xxx.ext"
        ]);

        $appraisal = Appraisal::findOrFail($request->appraisal_id);

        // Normalisasi file list
        $current = json_decode($appraisal->file ?? '[]', true) ?: [];
        if (!is_array($current)) $current = [$current];

        // Buang item
        $toRemove = $request->path;
        $updated  = array_values(array_filter($current, fn($p) => $p !== $toRemove));

        // (Opsional) hapus file fisik di storage/public
        $diskPath = Str::after($toRemove, 'storage/');
        if ($diskPath && Storage::disk('public')->exists($diskPath)) {
            Storage::disk('public')->delete($diskPath);
        }

        // Simpan kembali, jika kosong set null
        $appraisal->file = empty($updated) ? null : json_encode($updated);
        $appraisal->save();

        return response()->json(['success' => true, 'files' => $updated]);
    }

}