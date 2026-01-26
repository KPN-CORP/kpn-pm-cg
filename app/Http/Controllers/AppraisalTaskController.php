<?php

namespace App\Http\Controllers;

use App\Models\Achievements;
use App\Models\Appraisal;
use App\Models\AppraisalContributor;
use App\Models\Approval;
use App\Models\ApprovalLayerAppraisal;
use App\Models\ApprovalLog;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSnapshots;
use App\Models\Calibration;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use App\Models\Goal;
use App\Models\KpiCompany;
use App\Models\KpiUnits;
use App\Models\MasterCalibration;
use App\Services\AppService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpParser\Node\Expr\Empty_;
use stdClass;

use function PHPUnit\Framework\isEmpty;

class AppraisalTaskController extends Controller
{
    protected $category;
    protected $user;
    protected $appService;
    protected $period;

    public function __construct(AppService $appService)
    {
        $this->appService = $appService;
        $this->user = Auth::user()->employee_id;
        $this->category = 'Appraisal';
        $this->period = $this->appService->appraisalPeriod();
    }

    public function index(Request $request) {
        try {
            // Eager loading related models
            $employee = EmployeeAppraisal::with(['schedule'])->first();
            
            $user = $this->user;
            $period = $this->appService->appraisalPeriod();
            $filterYear = $request->input('filterYear');
            
            // Get dataTeams and filter contributors in one pass
            $dataTeams = ApprovalLayerAppraisal::with(['approver', 'contributors' => function($query) use ($user, $period) {
                $query->where('contributor_id', $user)->where('period', $period);
            }, 'goal' => function($query) use ($period) {
                $query->where('period', $period);
            }])
            ->where('approver_id', $user)
            ->where('layer_type', 'manager')
            ->whereHas('employee', function ($query) {
                $query->where(function($q) {
                    $q->whereRaw('json_valid(access_menu)')
                      ->whereJsonContains('access_menu', ['createpa' => 1]);
                });
            })
            ->whereDoesntHave('appraisal', function ($query) {
                $query->where('form_status', 'Draft');
            })
            ->get();
    
            // Filter contributors for 'dataTeams' that are empty
            $filteredDataTeams = $dataTeams->filter(fn($item) => $item->contributors->isEmpty() && $item->goal->isNotEmpty());
            $notifDataTeams = $filteredDataTeams->count();
            
            // Get data360 and filter contributors and appraisal in one pass
            $data360 = ApprovalLayerAppraisal::with(['approver', 'contributors' => function($query) use ($period) {
                $query->where('contributor_id', Auth::user()->employee_id)->where('period', $period);
            }, 'appraisal' => function($query) use ($period) {
                $query->where('period', $period);
            }])
            ->where('approver_id', $user)
            ->whereNotIn('layer_type', ['manager', 'calibrator'])
            ->whereHas('employee', function ($query) {
                $query->where(function($q) {
                    $q->whereRaw('json_valid(access_menu)')
                      ->whereJsonContains('access_menu', ['createpa' => 1]);
                });
            })
            ->get()
            ->filter(fn($item) => $item->appraisal !== null && $item->contributors->isEmpty());
    
            $notifData360 = $data360->count();
            
            // Pluck contributors data
            $contributors = $data360->pluck('contributors');
            
            $parentLink = __('Appraisal');
            $link = __('Task Box');
    
            return view('pages.appraisals-task.app', compact('notifDataTeams', 'notifData360', 'contributors', 'link', 'parentLink'));
    
        } catch (Exception $e) {
            Log::error('Error in index method: ' . $e->getMessage());
    
            // Return empty data to be consumed in the Blade template
            return view('pages.appraisals-task.app', [
                'data' => [],
                'link' => 'Task Box',
                'parentLink' => 'Appraisal',
                'contributors' => [],
                'notifDataTeams' => null,
                'notifData360' => null
            ]);
        }
    }
    
public function getTeamData(Request $request)
    {
        $user = $this->user;
        $period = $this->appService->appraisalPeriod();
        $filterYear = $request->input('filterYear');
    
        $datas = ApprovalLayerAppraisal::with([
            'employee' => function ($query) {
                $query->whereRaw('json_valid(access_menu)')
                      ->whereJsonContains('access_menu', ['createpa' => 1]);
            },
            'approver',
            'contributors' => function ($query) use ($user, $period) {
                $query->where('contributor_id', $user)
                      ->where('period', $period);
            },
            'goal' => function ($query) use ($period) {
                $query->where('period', $period);
            },
            'approvalRequest' => function ($query) use ($period, $user) {
                $query->where('category', 'Appraisal')
                      ->where('period', $period)
                      ->where('current_approval_id', $user);
            },
            'appraisal'
        ])
        ->whereHas('employee', function ($query) {
            $query->whereRaw('json_valid(access_menu)')
                  ->whereJsonContains('access_menu', ['createpa' => 1]);
        })
        ->whereDoesntHave('appraisal', fn($q) => $q->where('form_status', 'Draft'))
        ->where('approver_id', $user)
        ->where('layer_type', 'manager')
        ->get();
    
        $datas->each(function ($item) use ($period) {
            $goalData = optional($item->goal->first())->form_data;
            $appraisalData = optional($item->contributors->first())->form_data;
    
            if (!$appraisalData) return;
    
            $goalDataArr = json_decode($goalData ?? '[]', true);
            $appraisalArr = json_decode($appraisalData ?? '[]', true);
            $employeeData = $item->employee ?? null;
            
            $formData = $this->appService->combineFormData(
                $appraisalArr,
                $goalDataArr,
                'employee',
                $employeeData,
                $period
            );
    
            // Simpan nilai total score secara fleksibel
            foreach ($formData as $key => $value) {
                if (Str::startsWith($key, 'total')) {
                    $column = Str::of($key)
                        ->replaceFirst('total', '')
                        ->snake()
                        ->__toString();
    
                    $item->{$column} = round((float) $value, 2);
                }
            }
    
            // Cek calibration
            $item->calibrationCheck = Calibration::where('employee_id', $item->employee_id)
                ->where('status', 'Approved')
                ->where('period', $period)
                ->exists();
        });
        
        $data = $datas->map(function ($team, $index) {
            $employee = $team->employee;
            $goal = $team->goal->first();
            $contributor = $team->contributors->first();
            $approvalReq = $team->approvalRequest->first();

    
            if (!$employee || !$goal) return null;
    
            // ambil semua kolom score yang muncul dari loop di atas
            $scoreKeys = collect($team->getAttributes())
                ->filter(fn($v, $k) => Str::contains($k, 'score'))
                ->keys()
                ->toArray();
    
            $scores = [];
            foreach ($scoreKeys as $key) {
                $scores[$key] = $team->{$key};
            }
    
            return [
                'index' => $index + 1,
                'employee' => [
                    'fullname'      => $employee->fullname ?? '-',
                    'employee_id'   => $employee->employee_id ?? '-',
                    'designation'   => $employee->designation_name ?? '-',
                    'office_area'   => $employee->office_area ?? '-',
                    'group_company' => $employee->group_company ?? '-',
                ],
                'kpi' => array_merge(['kpi_status' => (bool) $contributor], $scores),
                'calibrationCheck' => $team->calibrationCheck ?? false,
                'contributorStatus' => $contributor->status ?? '-',
                'approval_date' => $contributor
                    ? $this->appService->formatDate($contributor->created_at)
                    : ($approvalReq ? $this->appService->formatDate($approvalReq->created_at) : '-'),
                'action' => view('components.action-buttons', ['team' => $team])->render(),
            ];
        })->filter()->values();
    
        return response()->json($data);
    }

    public function get360Data(Request $request)
    {
        $user = $this->user;
        $period = $this->appService->appraisalPeriod();
        $filterYear = $request->input('filterYear');
    
        $datas = ApprovalLayerAppraisal::with([
            'employee' => function ($query) {
                $query->whereRaw('json_valid(access_menu)')
                      ->whereJsonContains('access_menu', ['accesspa' => 1]);
            },
            'approver',
            'contributors' => function ($query) use ($user, $period) {
                $query->where('contributor_id', $user)
                      ->where('period', $period);
            },
            'goal' => function ($query) use ($period) {
                $query->where('period', $period);
            },
            'approvalRequest' => function ($query) use ($period) {
                $query->where('category', 'Appraisal')
                      ->where('period', $period);
            }
        ])
        ->whereHas('employee', function ($query) {
            $query->whereRaw('json_valid(access_menu)')
                  ->whereJsonContains('access_menu', ['accesspa' => 1]);
        })
        ->where('approver_id', $user)
        ->whereIn('layer_type', ['peers', 'subordinate'])
        ->has('approvalRequest')
        ->get();
    
        $datas->each(function ($item) use ($period) {
            $goalData = optional($item->goal->first())->form_data;
            $appraisalData = optional($item->contributors->first())->form_data;
    
            if (!$appraisalData) return;
    
            $goalDataArr = json_decode($goalData ?? '[]', true);
            $appraisalArr = json_decode($appraisalData ?? '[]', true);
    
            $employeeData = $item->employee ?? null;
    
            $formData = $this->appService->combineFormData(
                $appraisalArr,
                $goalDataArr,
                'employee',
                $employeeData,
                $period
            );
    
            // Simpan nilai total score secara fleksibel
            foreach ($formData as $key => $value) {
                if (Str::startsWith($key, 'total')) {
                    $column = Str::of($key)
                        ->replaceFirst('total', '')
                        ->snake()
                        ->__toString();
    
                    $item->{$column} = round((float) $value, 2);
                }
            }
    
            // Cek calibration
            $item->calibrationCheck = Calibration::where('employee_id', $item->employee_id)
                ->where('status', 'Approved')
                ->where('period', $period)
                ->exists();
        });
    
        // Format data untuk DataTables
        $data = $datas->map(function ($team, $index) {
            $employee = $team->employee;
            $goal = $team->goal->first();
            $contributor = $team->contributors->first();
    
            if (!$employee || !$goal) return null;
    
            // Ambil semua kolom score yang ter-generate di atas
            $scoreKeys = collect($team->getAttributes())
                ->filter(fn($v, $k) => Str::contains($k, 'score'))
                ->keys()
                ->toArray();
    
            $scores = [];
            foreach ($scoreKeys as $key) {
                $scores[$key] = $team->{$key};
            }
    
            return [
                'index' => $index + 1,
                'employee' => [
                    'fullname'      => $employee->fullname,
                    'employee_id'   => $employee->employee_id,
                    'designation'   => $employee->designation_name,
                    'office_area'   => $employee->office_area,
                    'group_company' => $employee->group_company,
                    'category'      => $team->layer_type,
                    'status'        => $contributor->status ?? '-',
                ],
                'kpi' => array_merge(['kpi_status' => (bool) $contributor], $scores),
                'calibrationCheck' => $team->calibrationCheck ?? false,
                'contributorStatus' => $contributor->status ?? '-',
                'approval_date' => $contributor
                    ? $this->appService->formatDate($contributor->created_at)
                    : '-',
                'action' => view('components.action-buttons', ['team' => $team])->render(),
            ];
        })->filter()->values();
    
        return response()->json($data);
    }

    public function initiate(Request $request)
    {
        try {
            $user = $this->user;
        $period = $this->appService->appraisalPeriod();
        $id = decrypt($request->id);

        $createPA = $this->accessMenu($id)['createpa'];

        if (!$createPA) {
            Session::flash('error', "Employee not eligible to create Appraisal $period.");
            return redirect()->route('appraisals-task');
        }

        $achievements = Achievements::where('employee_id', $id)->where('period', $period)->get();

        $step = $request->input('step', 1);

        $approval = ApprovalLayerAppraisal::select('approver_id')->where('employee_id', $id)->where('layer_type', 'manager')->where('layer', 1)->first();

        $employee = EmployeeAppraisal::where('employee_id', $id)->first();

        $goal = Goal::with(['employee'])->where('employee_id', $id)->where('period', $period)->first();

        $calibrator = ApprovalLayerAppraisal::where('layer', 1)->where('layer_type', 'calibrator')->where('employee_id', $id)->value('approver_id');

        if (!$calibrator) {
            Session::flash('error', "No Layer assigned, please contact admin to assign layer");
            return redirect()->back();
        }
        
        if ($goal) {
            $goalData = json_decode($goal->form_data, true);

            // ambil KPI company
            $kpiCompanies = KpiCompany::where('employee_id', $id)
                ->where('period', $period)
                ->first();

            if (
                empty($kpiCompanies) ||
                empty($kpiCompanies->form_data)
            ) {
                // KPI tidak ada → actual = null
                foreach ($goalData as &$goalItem) {
                    $goalItem['actual'] = null;
                }
                unset($goalItem);
            } else {

                $kpiData = is_string($kpiCompanies->form_data)
                    ? json_decode($kpiCompanies->form_data, true)
                    : $kpiCompanies->form_data;

                if (!is_array($kpiData)) {
                    foreach ($goalData as &$goalItem) {
                        $goalItem['actual'] = null;
                    }
                    unset($goalItem);
                } else {
                    foreach ($goalData as $index => &$goalItem) {
                        $goalItem['actual'] = $kpiData[$index]['achievement'] ?? null;
                    }
                    unset($goalItem);
                }
            }
        } else {
            Session::flash('error', "Goal for $period are not found.");
            return redirect()->back();
        }

        $firstCalibrator = ApprovalLayerAppraisal::where('layer', 1)->where('layer_type', 'calibrator')->where('employee_id', $id)->value('approver_id');

        // Get form group appraisal
        $formGroupData = $this->appService->formGroupAppraisal($id, 'Appraisal Form', $period);
        
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

        $filteredFormDatas = [
            'viewCategory' => 'initiate',
            'filteredFormData' => $filteredFormData,
        ];

        $viewAchievement = $employee->group_company == 'Cement' ? true : false;
        
        $parentLink = __('Appraisal');
        $link = 'Initiate Appraisal';

        // Pass the data to the view
        return view('pages.appraisals-task.initiate', compact('step', 'parentLink', 'link', 'filteredFormDatas', 'formGroupData', 'goal', 'approval', 'goalData', 'user', 'ratings', 'employee', 'achievements', 'viewAchievement'));

        } catch (Exception $e) {
            Log::error('Error in initiate method: ' . $e->getMessage());
            Session::flash('error', 'Failed to load appraisal form: ' . $e->getMessage());
            return redirect()->route('appraisals-task');
        }
    }

    public function approval(Request $request)
    {
        $user = $this->user;
        $period = $this->appService->appraisalPeriod();

        $step = $request->input('step', 1);

        $approval = ApprovalLayerAppraisal::select('approver_id')->where('employee_id', $request->id)->where('layer', 1)->first();

        $goal = Goal::with(['employee'])->where('employee_id', $request->id)->where('period', $period)->first();

        if ($goal) {
            $goalData = json_decode($goal->form_data, true);
        } else {
            Session::flash('error', "Goals for not found.");
            return redirect()->back();
        }

        // Read the content of the JSON files
        $formGroupData = $this->appService->formGroupAppraisal($request->id, 'Appraisal Form Task', $period);
        
        // Validate formGroupData is not empty
        if (empty($formGroupData) || !isset($formGroupData['data']) || empty($formGroupData['data']['form_appraisals'])) {
            throw new Exception("Form group configuration is incomplete or missing for approval.");
        }
        
        $formTypes = $formGroupData['data']['form_names'] ?? [];
        $formDatas = $formGroupData['data']['form_appraisals'] ?? [];

        
        $filteredFormData = array_filter($formDatas, function($form) use ($formTypes) {
            return in_array($form['name'], $formTypes);
        });

        $filteredFormDatas = [
            'viewCategory' => 'initiate',
            'filteredFormData' => $filteredFormData,
        ];
                
        $parentLink = __('Appraisal');
        $link = 'Initiate Appraisal';

        // Pass the data to the view
        return view('pages.appraisals-task.approval', compact('step', 'parentLink', 'link', 'filteredFormDatas', 'formGroupData', 'goal', 'approval', 'goalData', 'user'));
    }

    public function review(Request $request)
    {
        $user = $this->user;
        $period = $this->appService->appraisalPeriod();
        $id = decrypt($request->id);   
        
        $type = $request->type;

        $step = $request->input('step', 1);
        
        $goals = Goal::with(['employeeAppraisal'])->where('employee_id', $id)->where('period', $period)->first();

        $achievements = Achievements::where('employee_id', $id)->where('period', $period)->get();
        
        $appraisal = Appraisal::with(['employee', 'approvalRequest' => function($query) use ($period) {
            $query->where('category', 'Appraisal')->where('period', $period);
        }])->where('employee_id', $id)->where('period', $period)->first();

        $approverId = ($type === 'onbehalf')
            ? $appraisal->approvalRequest->current_approval_id
            : Auth::user()->employee_id;

        $contributorCheck = AppraisalContributor::where('employee_id', $id)->where('period', $period)->first();
        $contributorTransaction = AppraisalContributor::where('employee_id', $id)
                                    ->where('period', $period)
                                    ->where('created_by', Auth::id())
                                    ->exists(); 

        $appraisalContributor = AppraisalContributor::where('employee_id', $id)->where('contributor_id', $approverId)->where('period', $period)->first();
        
        $manager = ApprovalLayerAppraisal::where('employee_id', $id)->where('approver_id', $approverId )->where('layer_type', 'manager')->first();    

        $approval = ApprovalLayerAppraisal::with('approver')->where('employee_id', $id)->where('approver_id', $approverId )->where('layer_type', '!=', 'calibrator')->first();

        $achievement = [];
        $goal['actual'] = [];
        $appraisalId = $contributorCheck->appraisal_id ?? null;

        if ($goals) {
            $goalData = json_decode($goals->form_data, true);
        } else {
            Session::flash('error', "Goals for not found.");
            return redirect()->back();
        }

        if ($appraisal) {
    
            $manager = ApprovalLayerAppraisal::where('employee_id', $appraisal->employee_id)->where('approver_id', $approverId )->where('layer_type', 'manager')->first();
            
            $approval = ApprovalLayerAppraisal::where('employee_id', $appraisal->employee_id)->where('approver_id', $approverId )->where('layer_type', '!=', 'calibrator')->first();

            $appraisalId = $appraisal->id;
            
            $data = json_decode($appraisal['form_data'], true);
    
            $achievement = array_filter($data['formData'], function ($form) {
                return $form['formName'] === 'KPI';
            });
                
            foreach ($achievement[0] as $key => $formItem) {
                if (isset($goalData[$key])) {
                    $combinedData[$key] = array_merge($formItem, $goalData[$key]);
                } else {
                    $combinedData[$key] = $formItem;
                }
            }

            // Read the contents of the JSON file
            $formData = json_decode($appraisal->form_data, true);
            
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
        }

        if($appraisalContributor){

            $formData = json_decode($appraisalContributor->form_data, true);


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
        }

        $form_name = $manager ? 'Appraisal Form Review' : 'Appraisal Form 360' ;

        $formGroupData = $this->appService->formGroupAppraisal($id, $form_name, $period);
        
        // Validate formGroupData is not empty
        if (empty($formGroupData) || !isset($formGroupData['data']) || empty($formGroupData['data']['form_appraisals'])) {
            throw new Exception("Form group configuration is incomplete or missing for review.");
        }
        
        $formTypes = $formGroupData['data']['form_names'] ?? [];
        $formDatas = $formGroupData['data']['form_appraisals'] ?? [];
        
        $filteredFormData = array_filter($formDatas, function($form) use ($formTypes) {
            return in_array($form['name'], $formTypes);
        });

        
        // Merge the scores
        if ($appraisal || $appraisalContributor) {
            $filteredFormData = $this->appService->mergeScores($formData, $filteredFormData);
        }
        
        $filteredFormDatas = [
            'viewCategory' => 'Review',
            'filteredFormData' => $filteredFormData,
        ];

        $ratings = $formGroupData['data']['rating'] ?? [];

        $employee = EmployeeAppraisal::where('employee_id', $id)->first();

        $viewAchievement = $employee->group_company == 'Cement' ? true : false;
        
        $parentLink = __('Appraisal');
        $link = 'Review Appraisal';

        // Pass the data to the view
        return view('pages.appraisals-task.review', compact('step', 'parentLink', 'link', 'filteredFormDatas', 'formGroupData', 'goal', 'goals', 'approval', 'goalData', 'user', 'achievement', 'appraisalId', 'ratings', 'appraisal', 'type', 'achievements', 'viewAchievement', 'contributorTransaction'));

    }

    public function revoke(Request $request)
    {
        $actor   = Auth::user();
        $period  = $this->appService->appraisalPeriod();
        $empId   = decrypt($request->id);
        $reason  = $request->input('reason') ?? '-';

        $employee = EmployeeAppraisal::where('employee_id', $empId)->first();
        if (! $employee) {
            return $request->ajax()
                ? response()->json(['ok'=>false,'message'=>'Employee tidak ditemukan.'], 404)
                : back()->with('error','Employee tidak ditemukan.');
        }

        try {
            DB::beginTransaction();

            $appraisal = Appraisal::where('employee_id', $empId)
                ->where('period', $period)
                ->lockForUpdate()
                ->first();

            $approvalReq = ApprovalRequest::where('employee_id', $empId)
                ->where('period', $period)
                ->where('category', 'Appraisal')
                ->lockForUpdate()
                ->first();

            if ($approvalReq) {
                $this->writeApprovalLog([
                    'module'              => 'approval_request',
                    'loggable_id'         => (string) $approvalReq->form_id,
                    'loggable_type'       => ApprovalRequest::class,
                    'approval_request_id' => $approvalReq->id,
                    'actor_employee_id'   => (string) ($actor->employee_id ?? ''),
                    'actor_role'          => optional($actor->roles->first())->name,
                    'action'              => 'REVOKE',
                    'status_from'         => (string) ($approvalReq->status ?? 'Unknown'),
                    'status_to'           => 'Revoked',
                    'flow_id'             => $approvalReq->approval_flow_id,
                    'step_from'           => (int) ($approvalReq->current_step ?? 0),
                    'step_to'             => (int) ($approvalReq->current_step ?? 0),
                    'approver_from'       => (string) ($approvalReq->current_approval_id ?? ''),
                    'approver_to'         => (string) ($approvalReq->current_approval_id ?? ''),
                    'comments'            => $reason,
                    'meta'                => [
                        'period'      => $period,
                        'employee_id' => $empId,
                        'category'    => 'Appraisal',
                    ],
                ]);
            }

            AppraisalContributor::where('employee_id', $empId)
                ->where('period', $period)
                ->delete();

            if ($approvalReq) {
                $approvalReq->update([
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]);
                $approvalReq->delete();
            }

            if ($appraisal) {
                $appraisal->delete();
            }

            DB::commit();

            // ===== AJAX response tanpa refresh =====
            if ($request->ajax()) {
                return response()->json([
                    'ok'           => true,
                    'message'      => 'Appraisal revoked successfully.',
                    'employee_id'  => $empId,
                    'row_key'      => $empId, // pakai untuk selector row
                ]);
            }

            return back()->with('success', 'Appraisal revoked successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            if ($request->ajax()) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'Revoke failed: '.$e->getMessage(),
                ], 500);
            }
            return back()->with('error', 'Revoke failed: '.$e->getMessage());
        }
    }


    private function writeApprovalLog(array $payload): void
    {
        // 1) File log
        Log::channel('audit')->info('AUDIT', $payload);

        // 2) DB insert (adaptif)
        $now      = now();
        $action   = strtoupper((string)($payload['action'] ?? 'UNKNOWN'));
        $comments = $payload['comments'] ?? null;
        $actorEmp = (string)($payload['actor_employee_id'] ?? '');
        $metaJson = empty($payload['meta']) ? null : json_encode($payload['meta'], JSON_UNESCAPED_UNICODE);

        try {
            $logs = DB::table('approval_logs')->insert([
                'approval_request_id' => $payload['approval_request_id'] ?? null,
                'actor_employee_id'   => $actorEmp,
                'action'              => $action,
                'comments'            => $comments,
                'acted_at'            => $now,
                'module'              => $payload['module'] ?? 'approval_request',
                'loggable_id'         => $payload['loggable_id'] ?? null,
                'loggable_type'       => $payload['loggable_type'] ?? null,
                'flow_id'             => $payload['flow_id'] ?? null,
                'step_from'           => $payload['step_from'] ?? null,
                'step_to'             => $payload['step_to'] ?? null,
                'status_from'         => $payload['status_from'] ?? null,
                'status_to'           => $payload['status_to'] ?? null,
                'approver_from'       => $payload['approver_from'] ?? null,
                'approver_to'         => $payload['approver_to'] ?? null,
                'actor_role'          => $payload['actor_role'] ?? null,
                'meta_json'           => $metaJson,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            // avoid dd() in production; log the result instead so execution continues
            Log::channel('audit')->info('AUDIT_DB_INSERT', ['insert_result' => $logs, 'payload' => $payload]);
            return;

        } catch (\Throwable $e) {
            Log::channel('audit')->error('AUDIT_DB_WRITE_FAILED', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    public function detail(Request $request)
    {
        try {
            $user = Auth::user()->employee_id;
            $filterYear = $request->input('filterYear');
            $contributorId = decrypt($request->id);

            $datasQuery = AppraisalContributor::with(['employee', 'goal' => function($query) {
                $query->where('period', $this->period);
            }])->where('id', $contributorId);
            
            $datas = $datasQuery->get();
                        
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
            $appraisalData = $datas->isNotEmpty() ? json_decode($datas->first()->form_data, true) : [];

            $achievements = Achievements::where('employee_id', $datasQuery->first()->employee_id)->where('period', $this->period)->get();
            $viewAchievement = $datasQuery->first()->employee->group_company == 'Cement' ? true : false;

            if (!Empty($appraisalData)) {
                $period = $datas->first()->period;
            } else {
                $period = $this->appService->appraisalPeriod();
            }

            $employeeData = $datas->first()->employee;

            // Setelah data digabungkan, gunakan combineFormData untuk setiap jenis kontributor

            $formGroupData = $this->appService->formGroupAppraisal($employeeData->employee_id, 'Appraisal Form', $period);
            
            if (!$formGroupData) {
                $appraisalForm = ['data' => ['formData' => []]];
            } else {
                $appraisalForm = $formGroupData;
            }
            
            $cultureData = $this->getDataByName($appraisalForm['data']['form_appraisals'], 'Culture') ?? [];
            $leadershipData = $this->getDataByName($appraisalForm['data']['form_appraisals'], 'Leadership') ?? [];
            $technicalData = $this->getDataByName($appraisalForm['data']['form_appraisals'], 'Technical') ?? [];
            $sigapData = $this->getDataByName($appraisalForm['data']['form_appraisals'], 'Sigap') ?? [];

            $formData = $this->appService->combineFormData($appraisalData, $goalData, 'employee', $employeeData, $period);

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
            $link = __('Details');

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

            return view('pages.appraisals-task.detail', compact('datas', 'link', 'parentLink', 'formData', 'uomOption', 'typeOption', 'goals', 'selectYear', 'appraisalData', 'achievements', 'viewAchievement'));

        } catch (Exception $e) {
            Log::error('Error in index method: ' . $e->getMessage());
            return redirect('appraisals-task')->with('error', 'Appraisal not found');
            // Return empty data to be consumed in the Blade template
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

    public function storeInitiate(Request $request)
    {
        $submit_status = 'Submitted';
        $submit_type = $request->submit_type == 'submit_draft' ? 'Draft' : 'Approved';
        $messages = $request->submit_type == 'submit_draft' ? 'Draft saved successfully.' : 'Appraisal submitted successfully.';
        $period = $this->appService->appraisalPeriod();

        // Validate the request data
        $validatedData = $request->validate([
            'form_group_id' => 'required|string',
            'employee_id' => 'required|string|size:11',
            'approver_id' => 'required|string|size:11',
            'formGroupName' => 'required|string|min:5|max:100',
            'formData' => 'required|array',
            'attachment'    => 'nullable|array',
            'attachment.*'  => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png|max:10240',
        ]);

        DB::beginTransaction(); // Start the database transaction

        try {
            $contributorData = ApprovalLayerAppraisal::select('approver_id', 'layer_type')->where('approver_id', Auth::user()->employee_id)->where('layer_type', 'manager')->where('employee_id', $validatedData['employee_id'])->first();

            // Extract formGroupName
            $formGroupName = $validatedData['formGroupName'];
            $formData = $validatedData['formData'];

            // Create the array structure
            $datas = [
                'formGroupName' => $formGroupName,
                'formData' => $formData,
            ];

            $goals = Goal::with(['employee'])->where('employee_id', $validatedData['employee_id'])->where('period', $period)->first();

            $goalData = json_decode($goals->form_data, true);

            $firstCalibrator = ApprovalLayerAppraisal::where('layer', 1)->where('layer_type', 'calibrator')->where('employee_id', $validatedData['employee_id'])->value('approver_id');

            $masterCalibration = MasterCalibration::where('period', $period)->first();

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

                // Create a new Appraisal instance and save the data
                $appraisal = new Appraisal;
                $appraisal->id = Str::uuid();
                $appraisal->goals_id = $goals->id;
                $appraisal->employee_id = $validatedData['employee_id'];
                $appraisal->form_group_id = $validatedData['form_group_id'];
                $appraisal->category = $this->category;
                $appraisal->form_data = json_encode($datas); // Store the form data as JSON
                $appraisal->form_status = $submit_status;
                $appraisal->period = $period;
                $appraisal->file = empty($paths) ? null : json_encode($paths);
                $appraisal->created_by = Auth::user()->id;
                
                $appraisal->save();
                
                $calibrationGroupID = $masterCalibration->id_calibration_group;

                $formDatas = $this->appService->combineFormData($datas, $goalData, $contributorData->layer_type, $goals->employee, $period);

                AppraisalContributor::create([
                    'appraisal_id' => $appraisal->id,
                    'employee_id' => $validatedData['employee_id'],
                    'contributor_id' => $contributorData->approver_id,
                    'contributor_type' => $contributorData->layer_type,
                    // Add additional data here
                    'form_data' => json_encode($datas),
                    'rating' => $formDatas['contributorRating'],
                    'status' => $submit_type,
                    'period' => $period,
                    'created_by' => Auth::user()->id
                ]);
                
                $snapshot =  new ApprovalSnapshots;
                $snapshot->id = Str::uuid();
                $snapshot->form_id = $appraisal->id;
                $snapshot->form_data = json_encode($datas);
                $snapshot->employee_id = $validatedData['employee_id'];
                $snapshot->created_by = Auth::user()->id;
                
                $snapshot->save();

                $approval = new ApprovalRequest();
                $approval->form_id = $appraisal->id;
                $approval->category = $this->category;
                $approval->period = $period;
                $approval->employee_id = $validatedData['employee_id'];
                $approval->current_approval_id = $validatedData['approver_id'];
                $approval->created_by = Auth::user()->id;
                $approval->status = $submit_type != 'Approved' ? 'Pending' : 'Approved';
                // Set other attributes as needed
                $approval->save();

                if ($submit_type === 'Approved') {
                    $calibration = new Calibration();
                    $calibration->id_calibration_group = $calibrationGroupID;
                    $calibration->appraisal_id = $appraisal->id;
                    $calibration->employee_id = $validatedData['employee_id'];
                    $calibration->approver_id = $firstCalibrator;
                    $calibration->period = $period;
                    $calibration->created_by = Auth::user()->id;
    
                    $calibration->save();
                }

                DB::commit(); // Commit the transaction

                // Return a response, such as a redirect or a JSON response
                return redirect('appraisals-task')->with('success', 'Appraisal submitted successfully.');

        } catch (Exception $e) {
            DB::rollBack(); // Rollback the transaction in case of an error
            return redirect('appraisals-task')->with('error', $e->getMessage());
        }
    }

    public function storeReview(Request $request)
    {
        try {

            $period = $this->appService->appraisalPeriod();
            $submit_status = $request->submit_type == 'submit_draft' ? 'Draft' : 'Approved';
            $messages = $request->submit_type == 'submit_draft' ? 'Draft saved successfully.' : 'Appraisal submitted successfully.';
            $type = $request->type;

            $onbehalfMessages = $type === 'onbehalf' ? 'Approved by admin ' . Auth::user()->id : null;

            // Validate the request data
            $validatedData = $request->validate([
                'employee_id' => 'required|string|size:11',
                'approver_id' => 'required|string|size:11',
                'formGroupName' => 'required|string|min:5|max:100',
                'formData' => 'required|array',
                // 'attachment'    => 'nullable|array',
                // 'attachment.*'  => 'file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png|max:10240',
            ]);

            DB::beginTransaction(); // Start the database transaction

            $contributorData = ApprovalLayerAppraisal::select('approver_id', 'layer_type')
                ->where('approver_id', $validatedData['approver_id'])
                ->where('layer_type', '!=', 'calibrator')
                ->where('employee_id', $validatedData['employee_id'])
                ->first();

            $goals = Goal::with(['employee'])
                ->where('employee_id', $validatedData['employee_id'])
                ->where('period', $period)
                ->first();

            $goalData = json_decode($goals->form_data, true);

            // Extract formGroupName
            $formGroupName = $validatedData['formGroupName'];
            $formData = $validatedData['formData'];

            // Create the array structure
            $datas = [
                'formGroupName' => $formGroupName,
                'formData' => $formData,
            ];

            $formDatas = $this->appService->combineFormData($datas, $goalData, $contributorData->layer_type, $goals->employee, $period);

            $firstCalibrator = ApprovalLayerAppraisal::where('layer', 1)
                ->where('layer_type', 'calibrator')
                ->where('employee_id', $validatedData['employee_id'])
                ->value('approver_id');

            // Ambil appraisal_id dari request atau generate baru
            $appraisalId = $request->appraisal_id ?? Str::uuid();

            // Simpan atau update AppraisalContributor
            AppraisalContributor::updateOrCreate(
                [
                    'appraisal_id' => $request->appraisal_id, // tetap pakai original request untuk pencarian
                    'employee_id' => $validatedData['employee_id'],
                    'contributor_id' => $contributorData->approver_id,
                    'period' => $period,
                ],
                [
                    'appraisal_id' => $appraisalId, // yang disimpan, bisa UUID baru
                    'contributor_type' => $contributorData->layer_type,
                    'form_data' => json_encode($datas),
                    'rating' => $formDatas['contributorRating'],
                    'status' => $submit_status,
                    'created_by' => $request->userid
                ]
            );

            // Simpan ApprovalSnapshot menggunakan appraisalId yang pasti
            $snapshot = new ApprovalSnapshots();
            $snapshot->id = Str::uuid();
            $snapshot->form_id = $appraisalId;
            $snapshot->form_data = json_encode($datas);
            $snapshot->employee_id = $validatedData['employee_id'];
            $snapshot->created_by = $request->userid;
            $snapshot->save();

            if ($contributorData->layer_type == 'manager') {
                $nextLayer = ApprovalLayerAppraisal::where('approver_id', $validatedData['approver_id'])
                    ->where('layer_type', 'manager')
                    ->where('employee_id', $validatedData['employee_id'])
                    ->max('layer');

                // Find approver_id for the next layer
                $nextApprover = ApprovalLayerAppraisal::where('layer', $nextLayer + 1)
                    ->where('layer_type', 'manager')
                    ->where('employee_id', $validatedData['employee_id'])
                    ->value('approver_id');

                $firstCalibrator = ApprovalLayerAppraisal::where('layer', 1)
                    ->where('layer_type', 'calibrator')
                    ->where('employee_id', $validatedData['employee_id'])
                    ->value('approver_id');

                $masterCalibration = MasterCalibration::where('period', $period)->first();

                $calibrationGroupID = $masterCalibration->id_calibration_group;

                if (!$nextApprover) {
                    $approver = $validatedData['approver_id'];
                    $statusRequest = 'Approved';
                    $statusForm = 'Approved';
                } else {
                    $approver = $nextApprover;
                    $statusRequest = 'Pending';
                    $statusForm = 'Submitted';
                }

                $appraisal = Appraisal::where('id', $appraisalId)->first();

                // =========== Proses pengelolaan file attachment (jika ada) ===============
                // $existingRaw  = $appraisal->file;
                // $existingList = is_array($existingRaw) ? $existingRaw : (json_decode($existingRaw, true) ?: ($existingRaw ? [$existingRaw] : []));
                // $kept = $request['keep_files'] ?? [];      // bentuk "storage/files/appraisals/xxx.ext"
                // $kept = array_values(array_filter($kept, fn($p) => is_string($p) && $p !== ''));

                // // Hapus fisik file yang TIDAK di-keep
                // $toDelete = array_values(array_diff($existingList, $kept));
                // foreach ($toDelete as $webPath) {
                //     $diskPath = Str::after($webPath, 'storage/'); // "files/appraisals/xxx.ext"
                //     if ($diskPath && Storage::disk('public')->exists($diskPath)) {
                //         Storage::disk('public')->delete($diskPath);
                //     }
                // }
    
                // Lanjutkan proses update file, validasi, dll...
                // $timestamp  = Carbon::now()->format('His');
                // $baseDir    = 'files/docs_pa';                       // di disk 'public'
                // Storage::disk('public')->makeDirectory($baseDir);       // idempotent

                // $paths = [];
                // $filesInput = $request->file('attachment', []);

                // $files = [];
                // if ($filesInput instanceof UploadedFile) {
                //     $files = [$filesInput];
                // } elseif (is_array($filesInput)) {
                //     $files = array_values(array_filter($filesInput, fn($f) => $f instanceof UploadedFile));
                // }

                // (Opsional tapi disarankan) Validasi TOTAL 10MB (kept + new)
                // $totalBytesKept = array_sum(array_map(function ($webPath) {
                //     $diskPath = Str::after($webPath, 'storage/');
                //     return Storage::disk('public')->exists($diskPath) ? Storage::disk('public')->size($diskPath) : 0;
                // }, $kept));
                // $totalBytesNew = array_sum(array_map(fn(UploadedFile $f) => $f->getSize(), $files));
                // if (($totalBytesKept + $totalBytesNew) > 10 * 1024 * 1024) {
                //     return back()->withErrors(['attachment' => 'Total file size exceeds 10MB.'])->withInput();
                // }

                // $startIdx = count($kept);
                // foreach ($files as $i => $file) {
                //     if (!($file instanceof UploadedFile)) continue;
                //     $origName = $file->getClientOriginalName();
                //     $baseName = pathinfo($origName, PATHINFO_FILENAME);
                //     $clean = preg_replace('/[^\pL0-9 _.-]+/u', '', $baseName); // buang char aneh
                //     $clean = trim(preg_replace('/\s+/', ' ', $clean));         // rapikan spasi
                //     $clean = str_replace(' ', '_', mb_substr($clean, 0, 80));  // ganti spasi -> underscore
                //     $ext      = strtolower($file->getClientOriginalExtension() ?: $file->extension());
                //     $seq = str_pad($startIdx + $i + 1, 2, '0', STR_PAD_LEFT);
                //     $safeExt  = $ext ?: 'bin';
                //     $filename = "{$clean}_{$period}_{$timestamp}_{$seq}.{$safeExt}";

                //     Storage::disk('public')->putFileAs($baseDir, $file, $filename);

                //     // Simpan path web (akses via /storage)
                //     $paths[] = "storage/{$baseDir}/{$filename}";
                // }

                // $finalPaths = array_values(array_merge($kept, $paths));


                if ($submit_status === 'Approved') {
                    $calibration = Calibration::updateOrCreate(
                        [
                            'id_calibration_group' => $calibrationGroupID,
                            'appraisal_id' => $appraisalId,
                            'employee_id' => $validatedData['employee_id'],
                            'approver_id' => $firstCalibrator,
                            'period' => $period,
                        ],
                        [
                            'created_by' => $request->userid,
                        ]
                    );

                    if ($calibration) {

                        $appraisal->update([
                            'form_data' => json_encode($datas),
                            'form_status' => $statusForm,
                            'updated_by' => $request->userid,
                            // 'file' => empty($finalPaths) ? null : json_encode($finalPaths),
                        ]);
    
                        ApprovalRequest::where('form_id', $calibration->appraisal_id)
                            ->update([
                                'current_approval_id' => $approver,
                                'status' => $statusRequest,
                                'updated_by' => $request->userid,
                                'messages' => $onbehalfMessages,
                            ]);
    
                        Approval::updateOrCreate(
                            [
                                'request_id' => ApprovalRequest::where('form_id', $calibration->appraisal_id)->value('id'),
                                'approver_id' => $validatedData['approver_id'],
                            ],
                            [
                                'created_by' => $request->userid,
                                'status' => 'Approved',
                            ]
                        );
                    }
                } else {
    
                        $appraisal->update([
                            'updated_by' => $request->userid,
                            'file' => empty($finalPaths) ? null : json_encode($finalPaths),
                        ]);
                }

            }

            DB::commit(); // Commit the transaction

            // Return a response, such as a redirect or a JSON response
            if ($type === 'onbehalf') {
                return redirect()->route('onbehalf');
            }

            return redirect('appraisals-task')->with('success', $messages);

        } catch (Exception $e) {
            DB::rollBack(); // Rollback the transaction in case of an error
            Log::error('Error in storeReview method: ' . $e->getMessage());
            if ($type === 'onbehalf') {
                return redirect()->route('onbehalf')->with('error', 'An error occurred while submitting the appraisal.');
            }
            return redirect('appraisals-task')->with('error', 'An error occurred while submitting the appraisal.');
        }
    }

    private function accessMenu($id)
    {
        $accessMenu = [];

        $employee = EmployeeAppraisal::where('employee_id', $id)->first();
        if ($employee) {
            $accessMenu = json_decode($employee->access_menu, true);
        }

        return $accessMenu;
    }

}