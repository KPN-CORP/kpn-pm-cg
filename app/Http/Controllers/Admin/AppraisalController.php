<?php

namespace App\Http\Controllers\Admin;

use App\Events\FileReadyNotification;
use App\Exports\AppraisalDetailExport;
use App\Http\Controllers\Controller;
use App\Jobs\ExportAppraisalDetails;
use App\Models\Appraisal;
use App\Models\AppraisalContributor;
use App\Models\ApprovalLayerAppraisal;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSnapshots;
use App\Models\Calibration;
use App\Models\EmployeeAppraisal;
use App\Models\FormGroupAppraisal;
use App\Models\MasterRating;
use App\Models\MasterWeightage;
use Illuminate\Http\Request;
use App\Services\AppService;
use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

use function Pest\Laravel\json;

class AppraisalController extends Controller
{
    protected $category;
    protected $user;
    protected $appService;
    protected $roles;

    public function __construct(AppService $appService)
    {
        $this->appService = $appService;
        $this->user = Auth::user()->employee_id;
        $this->category = 'Appraisal';
        $this->roles = Auth::user()->roles;
    }

    public function index(Request $request)
    {

        ini_set('max_execution_time', 300);

        $userID = Auth::id();

        $restrictionData = $this->getRestrictionData();
        $filterInputs = $this->getFilterInputs($request, $restrictionData);
        $criteria = $this->buildCriteria($restrictionData);
        $query = $this->buildAppraisalQuery($criteria, $filterInputs);
        
        $datas = $this->transformAppraisalData($query->get(), $filterInputs['period']);
        
        $layerHeaders = $this->getLayerHeaders($datas);
        $groupCompanies = $this->getDistinctValues('group_company', $criteria);
        $companies = $this->getDistinctCompany('company_name', $criteria);
        $locations = $this->getDistinctValues('office_area', $criteria);
        $units = $this->getDistinctValues('unit', $criteria);

        $reportFiles = $this->getReportFiles($userID);
        $jobs = $this->getExportJobs($userID);
        
        $maxCalibrator = $datas->pluck('approvalStatus.calibrator')->flatten()->count();

        $layerBody = $maxCalibrator > 0 
        ? array_map(fn ($i) => ($i + 1), range(0, min($maxCalibrator - 1, 9))) 
        : [];
        
        $parentLink = __('Reports');
        $link = __('Appraisal');

        return view('pages.appraisals.admin.app', compact(
            'datas', 'layerHeaders', 'layerBody', 'filterInputs', 'groupCompanies', 'companies', 'units', 'locations', 'reportFiles', 'jobs', 'parentLink', 'link'
        ));
    }

    private function getRestrictionData()
    {
        $role = Auth::user()->roles->first();
        return $role ? json_decode($role->restriction, true) : [];
    }

    private function getFilterInputs(Request $request, $restriction)
    {
        // Retrieve group_company input
        $groupCompanyInput = $request->input('group_company', []);

        // Handle default and multiple values for group_company
        if (empty($groupCompanyInput)) {
            if (empty($restriction['group_company'])) {
                $groupCompanyInput = 'KPN Corporation'; // Default when empty
            } else {
                $groupCompanyInput = reset($restriction['group_company']); // Default when empty
            }
        } else {
            $groupCompanyInput = $groupCompanyInput; // Select only the first value
        }

        return [
            'filter_year' => $request->input('filter_year', ''),
            'group_company' => $groupCompanyInput,
            'company' => $request->input('company', []),
            'location' => $request->input('location', []),
            'unit' => $request->input('unit', []),
            'period' => $request->input('filter_year', '') ?: app('App\Services\AppService')->appraisalPeriod(),
        ];
    }

    private function buildCriteria(array $restrictionData)
    {
        return [
            'work_area_code' => $restrictionData['work_area_code'] ?? [],
            'group_company' => $restrictionData['group_company'] ?? [],
            'contribution_level_code' => $restrictionData['contribution_level_code'] ?? [],
        ];
    }

    private function buildAppraisalQuery(array $criteria, array $filters)
    {
        return EmployeeAppraisal::with([
            'appraisal' => function ($query) use ($filters) {
                $query->where('period', $filters['period']);
            },
            'appraisalLayer.approver',
            'appraisalContributor' => function ($query) use ($filters) {
                $query->where('period', $filters['period']);
            },
            'calibration' => function ($query) use ($filters) {
                $query->where('period', $filters['period']);
            },
            'appraisal.formGroupAppraisal',
        ])
        ->where(function ($query) use ($criteria) {
            foreach ($criteria as $key => $value) {
                if (!empty($value)) {
                    $query->whereIn($key, $value);
                }
            }

        })             
        ->when($filters['group_company'], function ($query, $groupCompany) {
            $query->where('group_company', $groupCompany);
        })
        ->when($filters['company'], function ($query, $company) {
            $query->whereIn('contribution_level_code', $company);
        })
        ->when($filters['location'], function ($query, $location) {
            $query->whereIn('office_area', $location);
        })
        ->when($filters['unit'], function ($query, $unit) {
            $query->whereIn('unit', $unit);
        });
    }

    private function transformAppraisalData($data, $period)
    {
        return $data->map(function ($employee) use ($period) {
            $approvalStatus = $this->buildApprovalStatus($employee, $period);
            $popoverContent = $this->generatePopoverContent($approvalStatus);
            if ($employee->appraisal->first()) {
                # code...
                $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                    ->where('id_rating_group', $employee->appraisal->first()->formGroupAppraisal->id_rating_group)
                    ->get();
                $convertRating = [];
    
                foreach ($masterRating as $rating) {
                    $convertRating[$rating->value] = $rating->parameter;
                }
                $appraisal =  $employee->appraisal->first()->rating
                                ? $convertRating[$employee->appraisal->first()->rating] 
                                : '-';
            }else{
                $appraisal = '-';
            }

            return [
                'id' => $employee->employee_id,
                'name' => $employee->fullname,
                'groupCompany' => $employee->group_company,
                'accessPA' => optional(json_decode($employee->access_menu, true))['createpa'] ?? 0,
                'appraisalStatus' => $employee->appraisal->first(),
                'finalScore' => $appraisal,
                'approvalStatus' => $approvalStatus,
                'popoverContent' => $popoverContent,
            ];

        });
    }

    private function buildApprovalStatus($employee, $period)
    {
        $status = [];
        foreach ($employee->appraisalLayer as $layer) {
            
            $availability = $this->checkLayerAvailability($layer, $employee->employee_id, $period);

            if ($employee->appraisal->first()) {

                # code...
                $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                    ->where('id_rating_group', $employee->appraisal->first()->formGroupAppraisal->id_rating_group)
                    ->get();
                $convertRating = [];
    
                foreach ($masterRating as $rating) {
                    $convertRating[$rating->value] = $rating->parameter;
                }

                $rated =  $availability['rating']
                                ? '|' . $convertRating[$availability['rating']] 
                                : '|-';
            }else{
                $rated = '|-';
            }

            $status[$layer->layer_type][] = [
                'approver_id' => $layer->approver_id,
                'layer' => $layer->layer,
                'rating' => $rated, // Include rating if available
                'status' => $availability['exists'], // Keep the availability status
                'approver_name' => $layer->approver->fullname ?? 'N/A',
            ];
        }
        return $status;
    }

    private function checkLayerAvailability($layer, $employeeId, $period)
    {
        if ($layer->layer_type === 'calibrator') {
            $calibration = Calibration::where('approver_id', $layer->approver_id)
                ->where('employee_id', $employeeId)
                ->where('period', $period)
                ->where('status', 'Approved')
                ->first();

            return [
                'exists' => (bool) $calibration, // Availability status
                'rating' => $calibration->rating ?? null, // Rating if available
            ];
        } else {
            $contributorExists = AppraisalContributor::where('contributor_id', $layer->approver_id)
                ->where('employee_id', $employeeId)
                ->where('period', $period)
                ->exists();

            return [
                'exists' => $contributorExists,
                'rating' => null, // No rating for contributors
            ];
        }
    }

    private function generatePopoverContent(array $approvalStatus)
    {
        $content = [];
        
        foreach ($approvalStatus as $type => $layers) {
            // Sort layers by the "layer" key in ascending order
            usort($layers, function ($a, $b) {
                return $a['layer'] <=> $b['layer'];
            });

            foreach ($layers as $index => $layer) {
                $content[] = strtoupper(substr($type, 0, 1)) . ($index + 1) . ": " . $layer['approver_name'] . " (" . $layer['approver_id'] . ")";
            }
        }

        return implode("<br>", $content);
    }


    private function getLayerHeaders($datas)
    {
        $maxCalibrators = $datas->pluck('approvalStatus.calibrator')->flatten()->count();
        return array_map(fn ($i) => 'C' . ($i + 1), range(0, min($maxCalibrators - 1, 9)));
    }

    private function getDistinctValues($column, $criteria)
    {
        return EmployeeAppraisal::select($column)
            ->distinct()
            ->where(function ($query) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if (!empty($value)) {
                        $query->whereIn($key, $value);
                    }
                }
            })
            ->get();
    }

    private function getDistinctCompany($column, $criteria)
    {
        return EmployeeAppraisal::select('contribution_level_code', $column)
            ->distinct('contribution_level_code')
            ->where(function ($query) use ($criteria) {
                foreach ($criteria as $key => $value) {
                    if (!empty($value)) {
                        $query->whereIn($key, $value);
                    }
                }
            })
            ->get();
    }

    private function getReportFiles($userID)
    {
        $directory = 'exports';
        $filePrefix = 'appraisal_details_' . $userID;
    
        $files = collect(Storage::disk('public')->files($directory))
            ->filter(fn($file) => str_starts_with(basename($file), $filePrefix) && str_ends_with($file, '.xlsx'))
            ->map(fn($file) => [
                'name' => basename($file),
                'last_modified' => date('Y-m-d H:i:s', Storage::disk('public')->lastModified($file)),
            ])->toArray(); // Convert collection to array
    
        // Return the first file or null if no files found
        return reset($files); // `reset()` retrieves the first value
    }

    private function getExportJobs($userID)
    {
        return DB::table('jobs')
            ->where('payload', 'like', '%export_appraisal_reports_' . $userID . '%')
            ->get();
    }

    public function detail(Request $request)
    {
        $id = explode('_', decrypt($request->id))[0];
        $period = explode('_', decrypt($request->id))[1] ? explode('_', decrypt($request->id))[1] : $this->appService->appraisalPeriod();

        $data = EmployeeAppraisal::with(['appraisalLayer' => function ($query) {
            $query->where('layer_type', '!=', 'calibrator');
        }, 'appraisal' => function ($query) use ($period) {
            $query->where('period', $period);
        }])->where('employee_id', $id)->get();


        try {
            
            $data->map(function($item) use ($period) {

                $appraisal_id = $item->appraisal->first()->id;

                $item->appraisalLayer->map(function($subItem) use ($appraisal_id, $period) {
                    
                    $contributor = AppraisalContributor::select('id','appraisal_id','contributor_type','contributor_id')->where('contributor_type', $subItem->layer_type)->where('contributor_id', $subItem->approver_id)->where('appraisal_id', $appraisal_id)->where('period', $period)->first();
                    
                    $subItem->contributor = $contributor;
                    
                    return $subItem;
                });

                $item->join_date = $this->appService->formatDate($item->date_of_joining);
                                
                return $item;
            });

            $datas = $data->first();

            $form_id = $datas->appraisal->first()->id;

            // Convert array to collection and group by layer_type
            $groupedData = collect($datas->appraisalLayer)
            ->concat(
                // Tambahkan data untuk layer_type 'self' dari $datas->appraisal
                collect($datas->appraisal)->map(function ($selfItem) {
                    $selfItem->layer_type = 'self'; // Tambahkan layer_type 'self' ke data appraisal
                    $selfItem->layer = null; // Atur layer jika diperlukan
                    return $selfItem;
                })
            )
            ->groupBy('layer_type')
            ->map(function ($items, $layerType) {
                // Further group each layer_type by 'layer'
                return $items->groupBy('layer')->mapWithKeys(function ($layerGroup, $layer) use ($layerType) {
                    // Handle layer type name and layer-based key
                    if ($layerType === 'manager') {
                        return ['Manager' => $layerGroup];
                    } elseif ($layerType === 'peers') {
                        return ['P' . $layer => $layerGroup];
                    } elseif ($layerType === 'subordinate') {
                        return ['S' . $layer => $layerGroup];
                    } elseif ($layerType === 'self') {
                        return ['Self' => $layerGroup];
                    }
                });
            });            

            $parentLink = __('Reports');
            $link = __('Appraisal');

            $formGroup = FormGroupAppraisal::with(['rating'])->find($datas->appraisal->first()->form_group_id);
            
            $ratings = [];

            foreach ($formGroup->rating as $rating) {
                $ratings[$rating->value] = $rating->parameter;
            }

            $final_rating = '-';

            if ($datas->appraisal->first()->rating) {
                $final_rating = $ratings[$datas->appraisal->first()->rating];
            }

            return view('pages.appraisals.admin.detail', compact('datas', 'groupedData', 'parentLink', 'link', 'final_rating'));

        } catch (Exception $e) {
            Log::error('Error in index method: ' . $e->getMessage());

            return redirect()->route('admin.appraisal');
        }
    }

    public function getDetailData(Request $request)
    {
        try {
            $user = Auth::user()->employee_id;
            $period = $this->appService->appraisalPeriod();
            $contributorId = $request->id;

            $parts = explode('_', $contributorId);
            
            // Access the separated parts
            $id = $parts[0];
            $formId = $parts[1];

            if ($id == 'summary') {
                $datasQuery = AppraisalContributor::with(['employee'])->where('appraisal_id', $formId);
                $datas = $datasQuery->get();

                $checkSnapshot = ApprovalSnapshots::where('form_id', $formId)->where('created_by', $datas->first()->employee->id)
                    ->orderBy('created_at', 'desc');

                // Check if `datas->first()->employee->id` exists
                if ($checkSnapshot) {
                    $query = $checkSnapshot;
                }else{
                    $query = ApprovalSnapshots::where('form_id', $formId)
                    ->orderBy('created_at', 'asc');
                }
                
                $employeeForm = $query->first();

                $data = [];
                $appraisalDataCollection = [];
                $goalDataCollection = [];

                $formGroupContent = $this->appService->formGroupAppraisal($datas->first()->employee_id, 'Appraisal Form');
                
                if (!$formGroupContent) {
                    $appraisalForm = ['data' => ['formData' => []]];
                } else {
                    $appraisalForm = $formGroupContent;
                }
                
                $cultureData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Culture') ?? [];
                $leadershipData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Leadership') ?? [];
                $sigapData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Sigap') ?? [];
                
                
                if($employeeForm){

                    // Create data item object
                    $dataItem = new stdClass();
                    $dataItem->request = $employeeForm;
                    $dataItem->name = $employeeForm->name;
                    $dataItem->goal = $employeeForm->goal;
                    $data[] = $dataItem;
    
                    // Get appraisal form data for each record
                    $appraisalData = [];
                    
                    if ($employeeForm->form_data) {
                        $appraisalData = json_decode($employeeForm->form_data, true);
                        $contributorType = $employeeForm->contributor_type;
                        $appraisalData['contributor_type'] = 'employee';
                    }
    
                    // Get goal form data for each record
                    $goalData = [];
                    if ($employeeForm->goal && $employeeForm->goal->form_data) {
                        $goalData = json_decode($employeeForm->goal->form_data, true);
                        $goalDataCollection[] = $goalData;
                    }
                    
                    // Combine the appraisal and goal data for each contributor
                    $employeeData = $employeeForm->employee; // Get employee data
            
                    $formData[] = $appraisalData;

                }
                
                foreach ($datas as $request) {

                    // Create data item object
                    $dataItem = new stdClass();
                    $dataItem->request = $request;
                    $dataItem->name = $request->name;
                    $dataItem->goal = $request->appraisal->goal;
                    $data[] = $dataItem;
                    
                    // Get appraisal form data for each record
                    $appraisalData = [];

                    if ($request->form_data) {
                        $appraisalData = json_decode($request->form_data, true);
                        $contributorType = $request->contributor_type;
                        $appraisalData['contributor_type'] = $contributorType;
                    }

                    // Get goal form data for each record
                    $goalData = [];
                    if ($request->appraisal->goal && $request->appraisal->goal->form_data) {
                        $goalData = json_decode($request->appraisal->goal->form_data, true);
                        $goalDataCollection[] = $goalData;
                    }
                    
                    // Combine the appraisal and goal data for each contributor
                    $employeeData = $request->employee; // Get employee data
            
                    $formData[] = $appraisalData;

                }

                $jobLevel = $employeeData->job_level;

                $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $datas->first()->period)->first();
                            
                $weightageContent = json_decode($weightageData->form_data, true);
                
                $result = $this->appService->appraisalSummary($weightageContent, $formData, $employeeData->employee_id, $jobLevel);

                // $formData = $this->appService->combineFormData($result['summary'], $goalData, $result['summary']['contributor_type'], $employeeData, $request->period);     
                                
                $formData = $this->appService->combineSummaryFormData($result, $goalData, $employeeData, $request->period);

                if (isset($formData['kpiScore'])) {
                    $formData['kpiScore'] = round($formData['kpiScore'], 2);
                    $formData['cultureScore'] = round($formData['cultureScore'], 2);
                    $formData['leadershipScore'] = round($formData['leadershipScore'], 2);
                    $formData['sigapScore'] = round($formData['sigapScore'], 2);
                }

                foreach ($formData['formData'] as &$form) {
                    if ($form['formName'] === 'Leadership') {
                        foreach ($leadershipData as $index => $leadershipItem) {
                            foreach ($leadershipItem['items'] as $itemIndex => $item) {
                                if (isset($form[$index][$itemIndex])) {
                                    $form[$index][$itemIndex] = [
                                        'formItem' => $item,
                                        'score' => round($form[$index][$itemIndex]['average'], 2)
                                    ];
                                }
                            }
                            $form[$index]['title'] = $leadershipItem['title'];
                        }
                    }
                    
                    if ($form['formName'] === 'Culture') {
                        foreach ($cultureData as $index => $cultureItem) {
                            foreach ($cultureItem['items'] as $itemIndex => $item) {
                                if (isset($form[$index][$itemIndex])) {
                                    $form[$index][$itemIndex] = [
                                        'formItem' => $item,
                                        'score' => round($form[$index][$itemIndex]['average'], 2)
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
                
                }       
                
                $appraisalData = $formData;

            }elseif($id == 'employee'){

                $datas = Appraisal::with([
                    'employee', 
                    'approvalSnapshots' => function ($query) {
                        $query->orderBy('created_at', 'desc');
                    }
                ])->where('id', $formId)->get();

                $formattedData = $datas->map(function($item) {
                    $item->formatted_created_at = $this->appService->formatDate($item->created_at);
    
                    $item->formatted_updated_at = $this->appService->formatDate($item->updated_at);
                    
                    return $item;
                });
    
                $data = [];
                foreach ($formattedData as $request) {
                    $dataItem = new stdClass();
                    $dataItem->request = $request;
                    $dataItem->name = $request->name;
                    $dataItem->goal = $request->goal;
                    $data[] = $dataItem;
                }
    
                $goalData = $datas->isNotEmpty() ? json_decode($datas->first()->goal->form_data, true) : [];
                $appraisalData = $datas->isNotEmpty() ? json_decode($datas->first()->approvalSnapshots->form_data, true) : [];

                $appraisalData['contributor_type'] = "employee";

                $appraisalData = array($appraisalData);
    
                $employeeData = $datas->first()->employee;
    
                // Setelah data digabungkan, gunakan combineFormData untuk setiap jenis kontributor

                $formGroupContent = $this->appService->formGroupAppraisal($datas->first()->employee_id, 'Appraisal Form');
            
                if (!$formGroupContent) {
                    $appraisalForm = ['data' => ['formData' => []]];
                } else {
                    $appraisalForm = $formGroupContent;
                }
                
                $cultureData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Culture') ?? [];
                $leadershipData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Leadership') ?? [];
                $sigapData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Sigap') ?? [];
    
                $jobLevel = $employeeData->job_level;

                $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $datas->first()->period)->first();
                            
                $weightageContent = json_decode($weightageData->form_data, true);

                $result = $this->appService->appraisalSummary($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);

                // $formData = $this->appService->combineFormData($result['calculated_data'][0], $goalData, 'employee', $employeeData, $datas->first()->period);
                $formData = $this->appService->combineFormData($appraisalData[0], $goalData, 'employee', $employeeData, $datas->first()->period);
                if (isset($formData['kpiScore'])) {
                    $appraisalData['kpiScore'] = round($formData['kpiScore'], 2);
                    $appraisalData['cultureScore'] = round($formData['cultureScore'], 2);
                    $appraisalData['leadershipScore'] = round($formData['leadershipScore'], 2);
                    $appraisalData['sigapScore'] = round($formData['sigapScore'], 2);
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
                }
    
                $path = base_path('resources/goal.json');
                if (!File::exists($path)) {
                    $options = ['UoM' => [], 'Type' => []];
                } else {
                    $options = json_decode(File::get($path), true);
                }
    
                $uomOption = $options['UoM'] ?? [];
                $typeOption = $options['Type'] ?? [];
    
                $employee = EmployeeAppraisal::where('employee_id', $user)->first();
                if (!$employee) {
                    $access_menu = ['goals' => null];
                } else {
                    $access_menu = json_decode($employee->access_menu, true);
                }
                $goals = $access_menu['goals'] ?? null;
    
                $selectYear = ApprovalRequest::where('employee_id', $user)->where('category', $this->category)->select('created_at')->get();
                $selectYear->transform(function ($req) {
                    $req->year = Carbon::parse($req->created_at)->format('Y');
                    return $req;
                });

                $appraisalData = $formData;

            }else{

                $datasQuery = AppraisalContributor::with(['employee'])->where('id', $id);

                $datas = $datasQuery->get();
                
                $formattedData = $datas->map(function($item) {
                    $item->formatted_created_at = $this->appService->formatDate($item->created_at);
    
                    $item->formatted_updated_at = $this->appService->formatDate($item->updated_at);
                    
                    return $item;
                });
    
                $goalData = $datas->isNotEmpty() ? json_decode($datas->first()->appraisal->goal->form_data, true) : [];
                $appraisalData = $datas->isNotEmpty() ? json_decode($datas->first()->form_data, true) : [];
                
                $appraisalData['contributor_type'] = $datas->first()->contributor_type;
                
                $appraisalData = array($appraisalData);
                
                $employeeData = $datas->first()->employee;
                
                // Setelah data digabungkan, gunakan combineFormData untuk setiap jenis kontributor
                
                $formGroupContent = $this->appService->formGroupAppraisal($datas->first()->employee_id, 'Appraisal Form');
                
                if (!$formGroupContent) {
                    $appraisalForm = ['data' => ['formData' => []]];
                } else {
                    $appraisalForm = $formGroupContent;
                }
                
                $cultureData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Culture') ?? [];
                $leadershipData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Leadership') ?? [];
                $sigapData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Sigap') ?? [];
                
                $jobLevel = $employeeData->job_level;
                
                $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $datas->first()->period)->first();
                            
                $weightageContent = json_decode($weightageData->form_data, true);
                
                $result = $this->appService->appraisalSummary($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);

                
                $formData = $this->appService->combineFormData($appraisalData[0], $goalData, $datas->first()->contributor_type, $employeeData, $datas->first()->period);
                
                if (isset($formData['totalKpiScore'])) {
                    $appraisalData['kpiScore'] = round($formData['totalKpiScore'], 2);
                    $appraisalData['cultureScore'] = round($formData['totalCultureScore'], 2);
                    $appraisalData['leadershipScore'] = round($formData['totalLeadershipScore'], 2);
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
                }
    
                $path = base_path('resources/goal.json');
                if (!File::exists($path)) {
                    $options = ['UoM' => [], 'Type' => []];
                } else {
                    $options = json_decode(File::get($path), true);
                }
    
                $uomOption = $options['UoM'] ?? [];
                $typeOption = $options['Type'] ?? [];
    
                $employee = EmployeeAppraisal::where('employee_id', $user)->first();
                if (!$employee) {
                    $access_menu = ['goals' => null];
                } else {
                    $access_menu = json_decode($employee->access_menu, true);
                }
                $goals = $access_menu['goals'] ?? null;
    
                $selectYear = ApprovalRequest::where('employee_id', $user)->where('category', $this->category)->select('created_at')->get();
                $selectYear->transform(function ($req) {
                    $req->year = Carbon::parse($req->created_at)->format('Y');
                    return $req;
                });

                $appraisalData = $formData;

            }

            return view('components.appraisal-card', compact('datas', 'formData', 'appraisalData'));

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

    }

    public function exportAppraisalDetail(Request $request)
    {
        // Increase the PHP memory limit
        ini_set('memory_limit', '512M');
    
        $data = $request->input('data'); // Retrieve the data sent by DataTable
        $headers = $request->input('headers'); // Dynamic headers from the request
        $batchSize = $request->input('batchSize', 100);
        $period = $request->input('period');
        $userID = Auth::user()->id;
        
        $directory = 'exports';
        $temporary = 'temp';
        $filePrefix = 'appraisal_details_' . $userID;
        $tempFilePrefix = $userID . '_batch';

        // List all files in the directory
        $files = Storage::disk('public')->files($directory);
        $temporaryFiles = Storage::disk('public')->files($temporary);

        // Find and delete files matching the prefix, regardless of their extension
        foreach ($files as $file) {
            $baseName = pathinfo($file, PATHINFO_FILENAME); // Extract the file name without extension
            if ($baseName === $filePrefix) {
                Storage::disk('public')->delete($file);
                Log::info($userID . ' Old file deleted: ' . $file);
            }
        }

        foreach ($temporaryFiles as $file) {
            $baseName = pathinfo($file, PATHINFO_FILENAME); // Extract the file name without extension
            if (strpos($baseName, $tempFilePrefix) !== false) { // Check if $filePrefix is contained in $baseName
                Storage::disk('public')->delete($file);
                Log::info($userID . ' Old temp file deleted: ' . $file);
            }
        }

        $isZip = count($data) > $batchSize;

        $job = ExportAppraisalDetails::dispatch($this->appService, $data, $headers, $userID, $batchSize, Auth::user(), $period);

        // Log::info('Dispatched job:', ['job' => $job]);

        return response()->json([
            'message' => 'Export is being processed in the background.',
            'isZip' => $isZip
            // 'message' => 'Your file is being processed, you will be notified when it is ready for download.',
        ]);

        // $job = dispatch(new ExportAppraisalDetails($this->appService, $data, $headers));

        // // Return a response indicating the export is processing, and include a task ID if needed
        // return response()->json([
        //     'message' => 'Export is being processed in the background.',
        //     'job_id' => $job->getJobId() // Pass job ID to check the status later
        // ]);

    }

    public function checkFileAvailability(Request $request)
    {
        $fileName = $request->input('file'); // Get the file name from the request

        // Define the directory where files are stored
        $directory = 'exports';
        
        // Get all files in the directory
        $files = Storage::disk('public')->files($directory);
        
        // Search for a file with the given base name (ignoring extension)
        $matchingFile = collect($files)->first(function ($file) use ($fileName) {
            return pathinfo($file, PATHINFO_FILENAME) === $fileName;
        });
        
        // Check if a matching file was found
        if ($matchingFile) {
            return response()->json(['exists' => true, 'filePath' => $matchingFile]);
        } else {
            return response()->json(['exists' => false, 'message' => 'File not found.']);
        }
        
    }

    public function checkJobAvailability(Request $request)
    {
        $user = $request->input('user'); // Get the file name from the request

        $jobs = $this->getExportJobs($user);

        // Check if a matching file was found
        if ($jobs) {
            return response()->json(['exists' => true, 'message' => 'Jobs found.']);
        } else {
            return response()->json(['exists' => false, 'message' => 'Jobs not found.']);
        }
        
    }

    /**
     * Download a file from the 'exports' directory.
     *
     * @param  string  $fileName
     * @return \Illuminate\Http\Response
     */
    public function downloadFile($fileName)
    {
        $filePath = 'exports/' . $fileName;

        // Check if file exists and download
        if (Storage::disk('public')->exists($filePath)) {
            return response()->download(storage_path('app/public/' . $filePath));
        } else {
            return response()->json(['message' => 'File not found.'], 404);
        }
    }
    public function deleteFile($fileName)
    {
        $filePath = 'exports/' . $fileName;

        // Check if file exists and download
        if (Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        } else {
            return response()->json(['message' => 'File not found.'], 404);
        }
    }

}