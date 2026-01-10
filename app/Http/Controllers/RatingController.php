<?php

namespace App\Http\Controllers;

use App\Exports\EmployeeRatingExport;
use App\Exports\InvalidAppraisalRatingImport;
use App\Imports\AppraisalRatingImport;
use App\Models\Appraisal;
use App\Models\AppraisalContributor;
use App\Models\ApprovalLayerAppraisal;
use App\Models\ApprovalRequest;
use App\Models\Calibration;
use App\Models\Employee;
use App\Models\KpiUnits;
use App\Models\MasterCalibration;
use App\Models\MasterRating;
use App\Services\AppService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class RatingController extends Controller
{
    protected $category;
    protected $user;
    protected $appService;
    protected $period;

    public function __construct(AppService $appService)
    {
        $this->user = Auth::user()->employee_id;
        $this->appService = $appService;
        $this->category = 'Appraisal';
        $this->period = $this->appService->appraisalPeriod();
    }

    public function index(Request $request) {
        try {
            Log::info('Starting the index method.', ['user' => $this->user]);

            $amountOfTime = 100;
            ini_set('max_execution_time', $amountOfTime);
            $user = $this->user;
            $period = $this->appService->appraisalPeriod();

            $filterYear = $request->input('filterYear');

            // Get the KPI unit and calibration percentage
            $kpiUnit = KpiUnits::with(['masterCalibration' => function($query) {
                $query->where('period', $this->period);
            }])->where('employee_id', $user)->where('status_aktif', 'T')->where('periode', $this->period)->first();

            if (!$kpiUnit) {
                Log::warning('KPI Unit not set for the user.', ['user' => $user]);
                Session::flash('error', "Your KPI Unit not been set");
                Session::flash('errorTitle', "Cannot Initiate Rating");
            }

            Log::info('Fetching KPI unit and calibration percentage.', ['user' => $user, 'period' => $period, 'kpiUnit' => $kpiUnit]);

            $calibration = $kpiUnit->masterCalibration->percentage;
            $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                ->where('id_rating_group', $kpiUnit->masterCalibration->id_rating_group)
                ->get();

            Log::info('Fetched master ratings.', ['masterRatingCount' => $masterRating->count()]);

            // Query for all ApprovalLayerAppraisal data
            $allData = ApprovalLayerAppraisal::with(['employee'])
                ->where('approver_id', $user)
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
                ->where('approval_layer_appraisals.approver_id', $user)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $this->category)
                ->where('approval_requests.period', $this->period) // Apply $this->period to the relation
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
            })->map(function ($group) use ($dataWithRequests, $user) {
                Log::info('Processing group.', ['groupSize' => $group->count()]);

                // Fetch `withRequests` based on the user's criteria
                $withRequests = ApprovalLayerAppraisal::join('approval_requests', 'approval_requests.employee_id', '=', 'approval_layer_appraisals.employee_id')
                    ->where('approval_layer_appraisals.approver_id', $user)
                    ->where('approval_layer_appraisals.layer_type', 'calibrator')
                    ->where('approval_requests.category', $this->category)
                    ->where('approval_requests.period', $this->period) // Apply $this->period to the relation
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
            $ratingDatas = $datas->map(function ($group) use ($user) {
                Log::info('Processing rating data for group.', ['groupSize' => $group['with_requests']->count() + $group['without_requests']->count()]);

                // Preload all calibration data in bulk
                $calibration = Calibration::with(['approver'])->where('period', $this->period)
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
                $formId = $data->approvalRequest->where('category', 'Appraisal')->where('period', $this->period)->first()->form_id;

                // Cache suggested ratings
                if (!isset($suggestedRatings[$employeeId][$formId])) {
                    $suggestedRatings[$employeeId][$formId] = $this->appService->suggestedRating($employeeId, $formId, $this->period);
                }

                // Cache rating values
                if (!isset($ratingValues[$employeeId])) {
                    $ratingValues[$employeeId] = $this->appService->ratingValue($employeeId, $this->user, $this->period);
                }
                }

                // Process withRequests using preloaded data
                $withRequests = $group['with_requests']->map(function ($data) use ($user, $calibration, $suggestedRatings, $ratingValues) {
                    Log::info('Processing withRequests item.', ['itemId' => $data->id]);

                    $employeeId = $data->employee->employee_id;
                    $formId = $data->approvalRequest->where('category', 'Appraisal')->where('period', $this->period)->first()->form_id;

                    // Fetch calibration data for the current employee and appraisal
                    $calibrationData = $calibration[$employeeId][$formId] ?? collect();

                    // Find previous rating
                    $previousRating = $calibrationData->whereNotNull('rating')
                        ->where('approver_id', '!=', $user)
                        ->first();

                    // Calculate suggested rating
                    $suggestedRating = $suggestedRatings[$employeeId][$formId];

                    $data->suggested_rating = $calibrationData->where('approver_id', $user)->first()
                        ? $this->appService->convertRating(
                            $suggestedRating,
                            $calibrationData->where('approver_id', $user)->first()->id_calibration_group
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
                    $isCalibrator = $calibrationData->where('approver_id', $user)
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
                        $data->rating_status = $calibrationData->where('approver_id', $user)->first() ? $calibrationData->where('approver_id', $user)->first()->status : null;
                        $data->rating_approved_date = Carbon::parse($userCalibration->updated_at)->format('d M Y');
                    }

                    $data->onCalibratorPending = $calibrationData->where('approver_id', $user)->where('status', 'Pending')->count();

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
                $withoutRequests = $group['without_requests']->map(function ($data) use ($user, $calibration) {
                    Log::info('Processing withoutRequests item.', ['itemId' => $data->id]);

                    $data->suggested_rating = null;

                    $isCalibrator = Calibration::where('approver_id', $user)
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
            $calibrations = $datas->map(function ($group) use ($calibration, $user) {
                Log::info('Processing calibration results for group.', ['groupSize' => $group['with_requests']->count() + $group['without_requests']->count()]);

                // $onCalibratorPending = $group['with_requests']->where('approver_id', $user)->where('status', 'Pending')->count();
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

            dd($ratingDatas);

            return view('pages.rating.app', compact('ratingDatas', 'calibrations', 'masterRating', 'link', 'parentLink', 'activeLevel', 'id_calibration_group'));
        } catch (Exception $e) {
            Log::error('Error in index method: ' . $e->getMessage());
            return redirect()->route('appraisals');
        }
    }
    
    public function store(Request $request) {

        $validatedData = $request->validate([
            'id_calibration_group' => 'required|string',
            'approver_id' => 'required|string|size:11',
            'employee_id' => 'required|array',
            'appraisal_id' => 'required|array',
            'rating' => 'required|array',
        ]);
        

        $status = 'Approved';

        $id_calibration_group = $validatedData['id_calibration_group'];

        $employees = $validatedData['employee_id'];
        $appraisal_id = $validatedData['appraisal_id'];
        $rating = $validatedData['rating'];

        foreach ($employees as $index => $employee) {
            
            $nextApprover = $this->appService->processApproval($employee, $validatedData['approver_id']);

            $ratingData[$index] = [
                'employee_id' => $employee,
                'appraisal_id' => $appraisal_id[$index],
                'rating' => $rating[$index],
                'approver' => $nextApprover,
            ];

            $index++;
        }
        
        foreach ($ratingData as $rating) {

            $updated = Calibration::where('approver_id', $validatedData['approver_id'])
                ->where('employee_id', $rating['employee_id'])
                ->where('appraisal_id', $rating['appraisal_id'])
                ->where('period', $this->period)
                ->update([
                    'rating' => $rating['rating'],
                    'status' => $status,
                    'updated_by' => Auth::user()->id
                ]);

            // Optionally, check if update was successful
            if ($updated) {
                if ($rating['approver']) {
                    // Check if a calibration record already exists to avoid duplicates
                    $existingCalibration = Calibration::where('approver_id', $rating['approver']['next_approver_id'])
                        ->where('employee_id', $rating['employee_id'])
                        ->where('appraisal_id', $rating['appraisal_id'])
                        ->where('period', $this->period)
                        ->first();

                    if (!$existingCalibration) {
                        $calibration = new Calibration();
                        $calibration->id_calibration_group = $id_calibration_group;
                        $calibration->appraisal_id = $rating['appraisal_id'];
                        $calibration->employee_id = $rating['employee_id'];
                        $calibration->approver_id = $rating['approver']['next_approver_id'];
                        $calibration->period = $this->period;
                        $calibration->created_by = Auth::user()->id;
                        $calibration->save();
                    }
                } else {
                    Appraisal::where('id', $rating['appraisal_id'])
                        ->update([
                            'rating' => $rating['rating'],
                            'form_status' => 'Approved',
                            'updated_by' => Auth::user()->id
                    ]);
                }
            } else {
                return back()->with('error', 'No record found for employee ' . $rating['employee_id'] . ' in period '.$this->period.'.');
            }
        }

        return back()->with('success', 'Ratings submitted successfully.');

    }

    public function exportToExcel($level)
    {
        try {
            $amountOfTime = 300;
            ini_set('max_execution_time', $amountOfTime);
            $user = $this->user;
            $period = $this->appService->appraisalPeriod();

            // Get the KPI unit and calibration percentage
            $kpiUnit = KpiUnits::with(['masterCalibration'])
                ->where('employee_id', $user)
                ->where('status_aktif', 'T')
                ->where('periode', $this->period)
                ->first();

            if (!$kpiUnit) {
                Session::flash('error', "Your KPI unit data not found");
                return redirect()->back();
            }

            $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                ->where('id_rating_group', $kpiUnit->masterCalibration->id_rating_group)
                ->get();

            // Query for ApprovalLayerAppraisal data
            $allData = ApprovalLayerAppraisal::with(['employee'])
                ->where('approver_id', $user)
                ->whereHas('employee', function ($query) {
                    $query->where(function ($q) {
                        $q->whereRaw('json_valid(access_menu)')
                        ->whereJsonContains('access_menu', ['createpa' => 1]);
                    });
                })
                ->where('layer_type', 'calibrator')
                ->get();

            // Query for ApprovalLayerAppraisal data with approval requests
            $dataWithRequests = ApprovalLayerAppraisal::join('approval_requests', 'approval_requests.employee_id', '=', 'approval_layer_appraisals.employee_id')
                ->where('approval_layer_appraisals.approver_id', $user)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $this->category)
                ->where('approval_requests.period', $this->period)
                ->whereNull('approval_requests.deleted_at')
                ->select('approval_layer_appraisals.*')
                ->get()
                ->keyBy('id');

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
            })->map(function ($group) use ($dataWithRequests, $user) {
                $withRequests = ApprovalLayerAppraisal::join('approval_requests', 'approval_requests.employee_id', '=', 'approval_layer_appraisals.employee_id')
                    ->where('approval_layer_appraisals.approver_id', $user)
                    ->where('approval_layer_appraisals.layer_type', 'calibrator')
                    ->where('approval_requests.category', $this->category)
                    ->where('approval_requests.period', $this->period)
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

                return [
                    'with_requests' => $withRequests->values(),
                    'without_requests' => $group->filter(function ($item) use ($dataWithRequests) {
                        return !$dataWithRequests->has($item->id);
                    }),
                ];
            })->sortKeys();

            // Filter the grouped data to include only the specified level ("Level89")
            if (!isset($datas[$level])) {
                Log::warning("No data found for level: {$level}");
                return redirect()->route('rating')->with('error', "No data available for level: {$level}");
            }

            $ratingDatas = collect([$level => $datas[$level]])->map(function ($group) use ($user, $period) {
                // Preload all calibration data in bulk for the relevant period
                $calibration = Calibration::with(['approver'])
                    ->where('period', $period)
                    ->orderBy('id', 'asc')
                    ->get()
                    ->groupBy(['employee_id', 'appraisal_id']); // Group by employee_id and appraisal_id for easy access

                // Preload suggested ratings and rating values in bulk
                $suggestedRatings = [];
                $ratingValues = [];

                foreach ($group['with_requests'] as $data) {
                    $employeeId = $data->employee->employee_id;
                    $formId = $data->approvalRequest->where('period', $this->period)->first()->form_id;

                    if (!isset($suggestedRatings[$employeeId][$formId])) {
                        $suggestedRatings[$employeeId][$formId] = $this->appService->suggestedRating($employeeId, $formId, $this->period);
                    }

                    if (!isset($ratingValues[$employeeId])) {
                        $ratingValues[$employeeId] = $this->appService->ratingValue($employeeId, $this->user, $this->period);
                    }
                }

                // Process `with_requests`
                $withRequests = $group['with_requests']->map(function ($data) use ($user, $calibration, $suggestedRatings, $ratingValues) {

                    $employeeId = $data->employee->employee_id;
                    $formId = $data->approvalRequest->where('period', $this->period)->first()->form_id;

                    $calibrationData = $calibration[$employeeId][$formId] ?? collect();

                    $previousRating = $calibrationData->whereNotNull('rating')->first();
                    $suggestedRating = $suggestedRatings[$employeeId][$formId];

                    $data->suggested_rating = $calibrationData->where('approver_id', $user)->first()
                        ? $this->appService->convertRating(
                            $suggestedRating,
                            $calibrationData->where('approver_id', $user)->first()->id_calibration_group
                        )
                        : null;

                    $data->previous_rating = $previousRating
                        ? $this->appService->convertRating($previousRating->rating, $calibrationData->first()->id_calibration_group)
                        : null;

                    $data->rating_value = $ratingValues[$employeeId];

                    $isCalibrator = $calibrationData->where('approver_id', $user)
                        ->where('status', 'Pending')
                        ->isNotEmpty();

                    $data->is_calibrator = $isCalibrator;
                    $data->rating_allowed = $this->appService->ratingAllowedCheck($employeeId);
                    $data->rating_incomplete = $calibrationData->whereNull('rating')->count();

                    $userCalibration = $calibrationData->first();
                    if ($userCalibration) {
                        $data->rating_status = $calibrationData->where('approver_id', $user)->first() ? $calibrationData->where('approver_id', $user)->first()->status : null;
                        $data->rating_approved_date = Carbon::parse($userCalibration->updated_at)->format('d M Y');
                    }

                    $currentCalibrator = $calibrationData->where('status', 'Pending')->first();
                    $data->current_calibrator = $currentCalibrator && $currentCalibrator->approver
                        ? $currentCalibrator->approver->fullname . ' (' . $currentCalibrator->approver->employee_id . ')'
                        : false;

                    return $data;
                });

                // Process `without_requests`
                $withoutRequests = $group['without_requests']->map(function ($data) use ($user, $calibration) {
                    $data->suggested_rating = null;

                    $isCalibrator = Calibration::where('approver_id', $user)
                        ->where('employee_id', $data->employee->employee_id)
                        ->where('status', 'Pending')
                        ->exists();

                    $data->is_calibrator = $isCalibrator;
                    $data->rating_allowed = $this->appService->ratingAllowedCheck($data->employee->employee_id);

                    $currentCalibrator = Calibration::with(['approver'])
                        ->where('employee_id', $data->employee->employee_id)
                        ->where('status', 'Pending')
                        ->first();

                    $data->current_calibrator = $currentCalibrator && $currentCalibrator->approver
                        ? $currentCalibrator->approver->fullname . ' (' . $currentCalibrator->approver->employee_id . ')'
                        : false;

                    return $data;
                });

                // Combine both `with_requests` and `without_requests` results
                return $withRequests->merge($withoutRequests);
            });

            // Prepare master ratings for mapping
            $ratings = [];
            foreach ($masterRating as $rating) {
                $ratings[$rating->value] = $rating->parameter;
            }

            // Return the Excel file for download
            return Excel::download(new EmployeeRatingExport($ratingDatas, $level, $ratings), 'employee_ratings_' . $level . '.xlsx');
        } catch (Exception $e) {
            Log::error('Error in exportToExcel method: ' . $e->getMessage());
            return redirect()->route('rating')->with('error', 'Failed to export data.');
        }
    }

    public function importFromExcel(Request $request)
    {
        $validatedData = $request->validate([
            'excelFile' => 'required|mimes:xlsx,xls,csv',
            'ratingQuotas' => 'required|string',
            'ratingCounts' => 'required|string',
        ]);

        // Muat file Excel ke dalam array
        $rows = Excel::toArray([], $validatedData['excelFile']);
        
        $data = $rows[0]; // Ambil sheet pertama
        $employeeIds = [];

        // Mulai dari indeks 1 untuk mengabaikan header
        for ($i = 1; $i < count($data); $i++) {
            $employeeIds[] = $data[$i][0];
        }

        
        $employeeIds = array_unique($employeeIds);

        // Ambil employee_ids dari data
        // $employeeIds = array_unique(array_column($data, 'employee_id'));
        try {

            // Get the KPI unit and calibration percentage
            $kpiUnit = KpiUnits::with(['masterCalibration'])->where('employee_id', $this->user)->where('status_aktif', 'T')->where('periode', $this->period)->first();

            $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                ->where('id_rating_group', $kpiUnit->masterCalibration->id_rating_group)
                ->get();

            $allowedRating = $masterRating->pluck('parameter')->toArray();
            
            // Get the ID of the currently authenticated user
            $userId = Auth::id();
            // $allowedRating = ;

            // Initialize the import process with the user ID
            $import = new AppraisalRatingImport($userId, $allowedRating, $validatedData['ratingQuotas'], $validatedData['ratingCounts']);

            // Retrieve invalid employees after import
            
            // Prepare the success message
            $message = 'Data imported successfully.';
            Excel::import($import, $request->file('excelFile'));
            
            $invalidEmployees = $import->getInvalidEmployees();

            // Check if there are any invalid employees
            if (!empty($invalidEmployees)) {
                // Append error information to the success message
                session()->put('invalid_employees', $invalidEmployees);

                // $message .= ' <u><a href="' . route('export.invalid.rating') . '">Click here to download the list of errors.</a></u>';
                $errorMessage = ' <u><a href="' . route('export.invalid.rating') . '">Click here to download the list of errors.</a></u>';
            }

            // If successful, redirect back with a success message
            if (!empty($invalidEmployees)) {
                return redirect()->route('rating')->with('error', 'An error occurred during the import process.')->with('errorMessage', $errorMessage);
            }else{ 
                return redirect()->route('rating')->with('success', $message);
            }
        } catch (ValidationException $e) {

            // Catch the validation exception and redirect back with the error message
            return redirect()->route('rating')->with('error', $e->errors()['error'][0]);
        } catch (\Exception $e) {
            // Handle any other exceptions
            return redirect()->route('rating')->with('error', 'An error occurred during the import process.');
        }

    }

    public function exportInvalidRating()
    {
        // Retrieve the invalid employees from the session or another source
        $invalidEmployees = session('invalid_employees');

        if (empty($invalidEmployees)) {
            return redirect()->back()->with('success', 'No invalid employees to export.');
        }

        // Export the invalid employees to an Excel file
        return Excel::download(new InvalidAppraisalRatingImport($invalidEmployees), 'errors_rating_import.xlsx');
    }

}
