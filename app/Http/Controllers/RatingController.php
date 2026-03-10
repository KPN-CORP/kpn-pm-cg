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
use App\Models\User;
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

    public function index(Request $request)
    {
        try {
            Log::info('Starting the index method.', ['user' => $this->user]);

            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');

            $user = $request->input('user') ?? $this->user;
            $period = $this->appService->appraisalPeriod();
            $filterYear = $request->input('filterYear');

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
                ->where('approver_id', $user)
                ->whereHas('employee', function ($query) {
                    $query->where(function ($q) {
                        $q->whereRaw('json_valid(access_menu)')
                            ->whereJsonContains('access_menu', ['createpa' => 1]);
                    });
                })
                ->where('layer_type', 'calibrator')
                ->get();

            if ($allData->isEmpty()) {
                Session::flash('error', "Schedule has been closed");
                Session::flash('errorTitle', "Cannot Initiate Rating");
                return back();
            }

            Log::info('Fetched all ApprovalLayerAppraisal data.', ['allDataCount' => $allData->count()]);

            // ─── 4. Query existence check: ALA ids yang punya approval_request ──
            $alaIdsWithRequests = ApprovalLayerAppraisal::join(
                'approval_requests',
                'approval_requests.employee_id',
                '=',
                'approval_layer_appraisals.employee_id'
            )
                ->where('approval_layer_appraisals.approver_id', $user)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $this->category)
                ->where('approval_requests.period', $this->period)
                ->whereNull('approval_requests.deleted_at')
                ->pluck('approval_layer_appraisals.id') // hanya ALA.id, tidak ada konflik
                ->flip();

            // ─── 5. JOIN query lengkap: selectRaw agar ALA.id TIDAK tertimpa AR.id ─
            $withRequestsRaw = ApprovalLayerAppraisal::join(
                'approval_requests',
                'approval_requests.employee_id',
                '=',
                'approval_layer_appraisals.employee_id'
            )
                ->where('approval_layer_appraisals.approver_id', $user)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $this->category)
                ->where('approval_requests.period', $this->period)
                ->whereNull('approval_requests.deleted_at')
                ->selectRaw('
                    approval_layer_appraisals.*,
                    approval_requests.*,
                    approval_layer_appraisals.id as id
                ')  // id di akhir = ALA.id (menimpa AR.id)
                ->get()
                ->groupBy('id')
                ->map(function ($subgroup) {
                    $appraisal = $subgroup->first();
                    $appraisal->approval_requests = $subgroup->first();
                    return $appraisal;
                });

            $dataWithRequestsById = $withRequestsRaw->keyBy('id');

            Log::info('Fetched withRequests data.', ['count' => $withRequestsRaw->count()]);

            // ─── 6. Kumpulkan semua employeeId & formId untuk preload ─────────────
            $allEmployeeIds = $allData->pluck('employee.employee_id')->unique()->values()->toArray();

            // form_id langsung dari JOIN
            $allFormIds = $withRequestsRaw->pluck('form_id')->filter()->unique()->values()->toArray();

            // ─── 7. Preload Calibration untuk semua employee+form sekaligus ────────
            $allCalibrations = Calibration::with(['approver'])
                ->where('period', $this->period)
                ->whereIn('employee_id', $allEmployeeIds)
                ->whereIn('status', ['Pending', 'Approved'])
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy(['employee_id', 'appraisal_id']);

            // ─── 8. Preload ratingValue (Calibration Approved) untuk semua employee ─
            $allRatingValues = Calibration::select('employee_id', 'approver_id', 'rating', 'status', 'period', 'appraisal_id')
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('approver_id', $this->user) // ratingValue default ke $this->user
                ->where('status', 'Approved')
                ->where('period', $this->period)
                ->get()
                ->keyBy('employee_id');

            // ─── 9. Preload ratingAllowedCheck untuk semua employee sekaligus ──────
            $allApprovalLayers = ApprovalLayerAppraisal::with(['approver', 'employee'])
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('layer_type', '!=', 'calibrator')
                ->get()
                ->groupBy('employee_id');

            $allAppraisalContributors = AppraisalContributor::where('period', $this->period)
                ->whereIn('employee_id', $allEmployeeIds)
                ->get()
                ->groupBy(function ($item) {
                    return $item->employee_id . '|' . $item->contributor_id;
                });

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
            $cachedSuggestedRatings = [];
            foreach ($allFormIds as $formId) {
                $itemForForm = $withRequestsRaw->first(fn($item) => (string) $item->form_id === (string) $formId);
                if ($itemForForm) {
                    $empId = $itemForForm->employee->employee_id;
                    if (!isset($cachedSuggestedRatings[$empId][$formId])) {
                        $cachedSuggestedRatings[$empId][$formId] = $this->appService->suggestedRating($empId, $formId, $this->period);
                    }
                }
            }

            // ─── 11. Preload Calibration untuk withoutRequests (hanya cek exists) ──
            $calibrationExistsByEmployee = Calibration::where('approver_id', $user)
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('status', 'Pending')
                ->pluck('employee_id')
                ->flip();

            // ─── 12. Group & map (tidak ada query di dalam loop) ──────────────────
            $datas = $allData->groupBy(function ($data) {
                return 'AllLevels';
            })->map(function ($group) use ($alaIdsWithRequests, $withRequestsRaw, $user) {
                $groupIds = $group->pluck('id')->flip();
                $withRequests = $withRequestsRaw
                    ->filter(fn($item) => $groupIds->has($item->id))
                    ->values();

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
            $ratingDatas = $datas->map(function ($group) use ($user, $period, $allCalibrations, $allRatingValues, $cachedSuggestedRatings, $convertRatingLocal, $ratingAllowedCheckLocal, $calibrationExistsByEmployee) {
                $withRequests = collect($group['with_requests'])->map(function ($data) use ($user, $period, $allCalibrations, $allRatingValues, $cachedSuggestedRatings, $convertRatingLocal, $ratingAllowedCheckLocal) {
                    $employeeId = $data->employee->employee_id;
                    $formId = $data->form_id;

                    $calibrationData = collect();
                    if ($formId && isset($allCalibrations[$employeeId][$formId])) {
                        $calibrationData = collect($allCalibrations[$employeeId][$formId]);
                    }

                    $previousRating = $calibrationData->whereNotNull('rating')
                        ->where('approver_id', '!=', $user)
                        ->first();

                    $suggestedRatingFloat = round($cachedSuggestedRatings[$employeeId][$formId] ?? 0, 2);

                    $calibratorEntry = $calibrationData->where('approver_id', $user)->first();
                    $data->suggested_rating = $calibratorEntry
                        ? $convertRatingLocal($suggestedRatingFloat, $calibratorEntry->id_calibration_group)
                        : null;

                    $data->suggested_request = $suggestedRatingFloat;
                    $data->id_calibration_group = $calibratorEntry?->id_calibration_group;

                    $firstCalibration = $calibrationData->first();
                    $data->previous_rating = $previousRating && $firstCalibration
                        ? $convertRatingLocal($previousRating->rating, $firstCalibration->id_calibration_group)
                        : null;
                    $data->previous_rating_name = $previousRating
                        ? $previousRating->approver->fullname . ' (' . $previousRating->approver->employee_id . ')'
                        : null;

                    $ratingRecord = $allRatingValues->get($employeeId);
                    $data->rating_value = $ratingRecord ? $ratingRecord->rating : null;

                    $data->is_calibrator = $calibrationData->where('approver_id', $user)
                        ->where('status', 'Pending')
                        ->isNotEmpty();

                    $data->rating_allowed = $ratingAllowedCheckLocal($employeeId);

                    $data->rating_incomplete = $calibrationData->whereNull('rating')->whereNull('deleted_at')->count();
                    $data->calibrationData = $calibrationData;

                    $userCalibration = $calibrationData->first();
                    if ($userCalibration) {
                        $myCalibration = $calibrationData->where('approver_id', $user)->first();
                        $data->rating_status = $myCalibration ? $myCalibration->status : null;
                        $data->rating_approved_date = Carbon::parse($userCalibration->updated_at)->format('d M Y');
                    }

                    $data->onCalibratorPending = $calibrationData->where('approver_id', $user)->where('status', 'Pending')->count();

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

                $withoutRequests = collect($group['without_requests'])->map(function ($data) use ($user, $ratingAllowedCheckLocal, $calibrationExistsByEmployee) {
                    $data->suggested_rating = null;
                    $data->suggested_request = 'withoutRequests';
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
            $calibrations = $datas->map(function ($group) use ($calibrationDistribution, $user) {
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

            Log::info('Determined active level.', ['activeLevel' => $activeLevel]);

            $parentLink = 'Calibration';
            $link = 'Rating';
            $id_calibration_group = 'c7b602c2-1791-4552-81e4-87525f8b0d83';

            Log::info('Returning view with data.', ['activeLevel' => $activeLevel, 'id_calibration_group' => $id_calibration_group]);

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
            Log::error('Error in index method: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->route('appraisals');
        }
    }

    public function store(Request $request)
    {

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
                return back()->with('error', 'No record found for employee ' . $rating['employee_id'] . ' in period ' . $this->period . '.');
            }
        }

        return back()->with('success', 'Ratings submitted successfully.');

    }

    public function exportToExcel($level)
    {
        try {
            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');

            $user = $this->user;
            $period = $this->appService->appraisalPeriod();

            // ─── 1. MASTER RATING ───────────────────────────
            $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                ->where('id_rating_group', '30e4e9eb-476f-4914-a123-807958a95260')
                ->get();

            // ─── 2. Preload MasterCalibration & MasterRating untuk convertRatingLocal ───
            $allMasterCalibrations = MasterCalibration::all()->keyBy('id_calibration_group');
            $allMasterRatings = MasterRating::all();
            $masterRatingLowest = $allMasterRatings->sortBy('value')->first();

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

            // ─── 3. ApprovalLayerAppraisal (All Data) ─────────────
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

            if ($allData->isEmpty()) {
                Session::flash('error', "Schedule has been closed");
                Session::flash('errorTitle', "Cannot Initiate Rating");
                return back();
            }

            // ─── 4. ALA Ids yang punya approval_requests ─────────
            $alaIdsWithRequests = ApprovalLayerAppraisal::join(
                'approval_requests',
                'approval_requests.employee_id',
                '=',
                'approval_layer_appraisals.employee_id'
            )
                ->where('approval_layer_appraisals.approver_id', $user)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $this->category)
                ->where('approval_requests.period', $this->period)
                ->whereNull('approval_requests.deleted_at')
                ->pluck('approval_layer_appraisals.id') // hindari konflik id
                ->flip();

            // ─── 5. JOIN lengkap: selectRaw menghindari id ter-overwrite ──
            $withRequestsRaw = ApprovalLayerAppraisal::join(
                'approval_requests',
                'approval_requests.employee_id',
                '=',
                'approval_layer_appraisals.employee_id'
            )
                ->where('approval_layer_appraisals.approver_id', $user)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $this->category)
                ->where('approval_requests.period', $this->period)
                ->whereNull('approval_requests.deleted_at')
                ->selectRaw('
                    approval_layer_appraisals.*,
                    approval_requests.*,
                    approval_layer_appraisals.id as id
                ')
                ->get()
                ->groupBy('id')
                ->map(function ($subgroup) {
                    $appraisal = $subgroup->first();
                    $appraisal->approval_requests = $subgroup->first();
                    return $appraisal;
                });

            Log::info('Fetched withRequests data.', ['count' => $withRequestsRaw->count()]);

            // Group the data berdasarkan AllLevels (struktur default ada)
            $datas = $allData->groupBy(function ($data) {
                return 'AllLevels';
            })->map(function ($group) use ($alaIdsWithRequests, $withRequestsRaw) {
                // Filter requests yang ada di group
                $groupIds = $group->pluck('id')->flip();

                $withRequests = $withRequestsRaw
                    ->filter(fn($item) => $groupIds->has($item->id))
                    ->values();

                $withoutRequests = $group
                    ->filter(fn($item) => !$alaIdsWithRequests->has($item->id))
                    ->values();

                return [
                    'with_requests' => $withRequests,
                    'without_requests' => $withoutRequests,
                ];
            })->sortKeys();

            if (!isset($datas[$level])) {
                Log::warning("No data found for level: {$level}");
                return redirect()->route('rating')->with('error', "No data available for level: {$level}");
            }

            // Kumpulkan ID untuk preloading dari filter level yang valid
            $levelGroup = $datas[$level];

            // Employee Ids pada level tersebut
            $allEmployeeIds = collect($levelGroup['with_requests'])
                ->concat($levelGroup['without_requests'])
                ->pluck('employee.employee_id')
                ->unique()
                ->values()
                ->toArray();

            // Form Ids
            $allFormIds = collect($levelGroup['with_requests'])
                ->pluck('form_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // ─── 6. Preload Calibration (Pending & Approved) ────────
            $allCalibrations = Calibration::with(['approver'])
                ->where('period', $this->period)
                ->whereIn('employee_id', $allEmployeeIds)
                // export membutuhkan semua status untuk tracking history & approver
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy(['employee_id', 'appraisal_id']);

            // ─── 7. Preload ratingValue (dari Calibration) ──────────
            $allRatingValues = Calibration::select('employee_id', 'approver_id', 'rating', 'status', 'period', 'appraisal_id')
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('approver_id', $user)
                ->where('status', 'Approved')
                ->where('period', $this->period)
                ->get()
                ->keyBy('employee_id');

            // ─── 8. Preload ratingAllowedCheckLocal ─────────────────
            $allApprovalLayers = ApprovalLayerAppraisal::with(['approver', 'employee'])
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('layer_type', '!=', 'calibrator')
                ->get()
                ->groupBy('employee_id');

            $allAppraisalContributors = AppraisalContributor::where('period', $this->period)
                ->whereIn('employee_id', $allEmployeeIds)
                ->get()
                ->groupBy(function ($item) {
                    return $item->employee_id . '|' . $item->contributor_id;
                });

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

                $review360Val = json_decode(optional($layers->first())->employee->access_menu ?? '{}', true)['review360'] ?? null;

                return [
                    'status' => true,
                    'message' => '360 Review completed',
                    'data' => $review360Val,
                ];
            };

            // ─── 9. Preload Suggested Rating ────────────────────────
            $cachedSuggestedRatings = [];
            foreach ($allFormIds as $formId) {
                // filter itemForForm
                $itemForForm = collect($levelGroup['with_requests'])->first(fn($item) => (string) $item->form_id === (string) $formId);
                if ($itemForForm) {
                    $empId = $itemForForm->employee->employee_id;
                    if (!isset($cachedSuggestedRatings[$empId][$formId])) {
                        $cachedSuggestedRatings[$empId][$formId] = $this->appService->suggestedRating($empId, $formId, $this->period);
                    }
                }
            }

            // ─── 10. Map output $ratingDatas ────────────────────────
            $ratingDatas = collect([$level => $levelGroup])->map(function ($group) use ($user, $period, $allCalibrations, $allRatingValues, $cachedSuggestedRatings, $convertRatingLocal, $ratingAllowedCheckLocal) {
                // Process `with_requests`
                $withRequests = collect($group['with_requests'])->map(function ($data) use ($user, $allCalibrations, $allRatingValues, $cachedSuggestedRatings, $convertRatingLocal, $ratingAllowedCheckLocal) {
                    $employeeId = $data->employee->employee_id;
                    $formId = $data->form_id;

                    $calibrationData = collect();
                    if ($formId && isset($allCalibrations[$employeeId][$formId])) {
                        $calibrationData = collect($allCalibrations[$employeeId][$formId]);
                    }

                    $previousRating = $calibrationData->whereNotNull('rating')->first();
                    $suggestedRatingFloat = round($cachedSuggestedRatings[$employeeId][$formId] ?? 0, 2);

                    $calibratorEntry = $calibrationData->where('approver_id', $user)->first();
                    $data->suggested_rating = $calibratorEntry
                        ? $convertRatingLocal($suggestedRatingFloat, $calibratorEntry->id_calibration_group)
                        : null;

                    $firstCalibration = $calibrationData->first();
                    $data->previous_rating = $previousRating && $firstCalibration
                        ? $convertRatingLocal($previousRating->rating, $firstCalibration->id_calibration_group)
                        : null;

                    $ratingRecord = $allRatingValues->get($employeeId);
                    $data->rating_value = $ratingRecord ? $ratingRecord->rating : null;

                    $data->is_calibrator = $calibrationData->where('approver_id', $user)
                        ->where('status', 'Pending')
                        ->isNotEmpty();

                    $data->rating_allowed = $ratingAllowedCheckLocal($employeeId);
                    $data->rating_incomplete = $calibrationData->whereNull('rating')->count();

                    $userCalibration = $calibrationData->first();
                    if ($userCalibration) {
                        $myCalibration = $calibrationData->where('approver_id', $user)->first();
                        $data->rating_status = $myCalibration ? $myCalibration->status : null;
                        $data->rating_approved_date = Carbon::parse($userCalibration->updated_at)->format('d M Y');
                    }

                    $currentCalibrator = $calibrationData->where('status', 'Pending')->first();
                    $data->current_calibrator = $currentCalibrator && $currentCalibrator->approver
                        ? $currentCalibrator->approver->fullname . ' (' . $currentCalibrator->approver->employee_id . ')'
                        : false;

                    return $data;
                });

                // Process `without_requests`
                $withoutRequests = collect($group['without_requests'])->map(function ($data) use ($user, $allCalibrations, $ratingAllowedCheckLocal) {
                    $employeeId = $data->employee->employee_id;
                    $data->suggested_rating = null;

                    // Menggunakan preload $allCalibrations yang sudah diload di atas
                    // flatMap digunakan karena array nya multidimensi keys nya form_id
                    $allCalsByEmployee = collect($allCalibrations->get($employeeId, []))->flatten(1);

                    $data->is_calibrator = $allCalsByEmployee
                        ->where('approver_id', $user)
                        ->where('status', 'Pending')
                        ->isNotEmpty();

                    $data->rating_allowed = $ratingAllowedCheckLocal($employeeId);

                    $currentCalibrator = $allCalsByEmployee
                        ->where('status', 'Pending')
                        ->first();

                    $data->current_calibrator = $currentCalibrator && $currentCalibrator->approver
                        ? $currentCalibrator->approver->fullname . ' (' . $currentCalibrator->approver->employee_id . ')'
                        : false;

                    return $data;
                });

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
            Log::error('Error in exportToExcel method: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->route('rating')->with('error', 'Failed to export data.');
        }
    }

    public function exportToExcelOnBehalf(Request $request)
    {
        try {
            $level = $request->input('level');
            $user = User::where('employee_id', $request->input('user'))->first()->employee_id;

            ini_set('max_execution_time', 300);
            ini_set('memory_limit', '512M');
            $period = $this->appService->appraisalPeriod();

            // ─── 1. MASTER RATING ───────────────────────────
            $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                ->where('id_rating_group', '30e4e9eb-476f-4914-a123-807958a95260')
                ->get();

            // ─── 2. Preload MasterCalibration & MasterRating untuk convertRatingLocal ───
            $allMasterCalibrations = MasterCalibration::all()->keyBy('id_calibration_group');
            $allMasterRatings = MasterRating::all();
            $masterRatingLowest = $allMasterRatings->sortBy('value')->first();

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

            // ─── 3. ApprovalLayerAppraisal (All Data) ─────────────
            $allData = ApprovalLayerAppraisal::with(['employee'])
                ->where('approver_id', $user)
                ->whereHas('employee', function ($query) {
                    $query->where(function ($q) {
                        $q->whereRaw('json_valid(access_menu)')
                            ->whereJsonContains('access_menu', ['accesspa' => 1]);
                    });
                })
                ->where('layer_type', 'calibrator')
                ->get();

            // ─── 4. ALA Ids yang punya approval_requests ─────────
            $alaIdsWithRequests = ApprovalLayerAppraisal::join(
                'approval_requests',
                'approval_requests.employee_id',
                '=',
                'approval_layer_appraisals.employee_id'
            )
                ->where('approval_layer_appraisals.approver_id', $user)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $this->category)
                ->where('approval_requests.period', $this->period)
                ->whereNull('approval_requests.deleted_at')
                ->pluck('approval_layer_appraisals.id')
                ->flip();

            // ─── 5. JOIN lengkap: selectRaw menghindari id ter-overwrite ──
            $withRequestsRaw = ApprovalLayerAppraisal::join(
                'approval_requests',
                'approval_requests.employee_id',
                '=',
                'approval_layer_appraisals.employee_id'
            )
                ->where('approval_layer_appraisals.approver_id', $user)
                ->where('approval_layer_appraisals.layer_type', 'calibrator')
                ->where('approval_requests.category', $this->category)
                ->where('approval_requests.period', $this->period)
                ->whereNull('approval_requests.deleted_at')
                ->selectRaw('
                    approval_layer_appraisals.*,
                    approval_requests.*,
                    approval_layer_appraisals.id as id
                ')
                ->get()
                ->groupBy('id')
                ->map(function ($subgroup) {
                    $appraisal = $subgroup->first();
                    $appraisal->approval_requests = $subgroup->first();
                    return $appraisal;
                });

            // Group the data berdasarkan AllLevels (struktur default ada)
            $datas = $allData->groupBy(function ($data) {
                return 'AllLevels';
            })->map(function ($group) use ($alaIdsWithRequests, $withRequestsRaw) {
                $groupIds = $group->pluck('id')->flip();

                $withRequests = $withRequestsRaw
                    ->filter(fn($item) => $groupIds->has($item->id))
                    ->values();

                $withoutRequests = $group
                    ->filter(fn($item) => !$alaIdsWithRequests->has($item->id))
                    ->values();

                return [
                    'with_requests' => $withRequests,
                    'without_requests' => $withoutRequests,
                ];
            })->sortKeys();

            if (!isset($datas[$level])) {
                Log::warning("No data found for level: {$level}");
                return redirect()->route('rating')->with('error', "No data available for level: {$level}");
            }

            // Kumpulkan ID untuk preloading dari filter level yang valid
            $levelGroup = $datas[$level];

            // Employee Ids pada level tersebut
            $allEmployeeIds = collect($levelGroup['with_requests'])
                ->concat($levelGroup['without_requests'])
                ->pluck('employee.employee_id')
                ->unique()
                ->values()
                ->toArray();

            // Form Ids
            $allFormIds = collect($levelGroup['with_requests'])
                ->pluck('form_id')
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            // ─── 6. Preload Calibration (Pending & Approved) ────────
            $allCalibrations = Calibration::with(['approver'])
                ->where('period', $this->period)
                ->whereIn('employee_id', $allEmployeeIds)
                ->orderBy('id', 'desc') // Memudahkan first() mengambil data terbaru
                ->get()
                ->groupBy(['employee_id', 'appraisal_id']);

            // ─── 7. Preload ratingValue (dari Calibration) ──────────
            $allRatingValues = Calibration::select('employee_id', 'approver_id', 'rating', 'status', 'period', 'appraisal_id')
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('approver_id', $user)
                ->where('status', 'Approved')
                ->where('period', $this->period)
                ->get()
                ->keyBy('employee_id');

            // ─── 8. Preload ratingAllowedCheckLocal ─────────────────
            $allApprovalLayers = ApprovalLayerAppraisal::with(['approver', 'employee'])
                ->whereIn('employee_id', $allEmployeeIds)
                ->where('layer_type', '!=', 'calibrator')
                ->get()
                ->groupBy('employee_id');

            $allAppraisalContributors = AppraisalContributor::where('period', $this->period)
                ->whereIn('employee_id', $allEmployeeIds)
                ->get()
                ->groupBy(function ($item) {
                    return $item->employee_id . '|' . $item->contributor_id;
                });

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

                $review360Val = json_decode(optional($layers->first())->employee->access_menu ?? '{}', true)['review360'] ?? null;

                return [
                    'status' => true,
                    'message' => '360 Review completed',
                    'data' => $review360Val,
                ];
            };

            // ─── 9. Preload Suggested Rating ────────────────────────
            $cachedSuggestedRatings = [];
            foreach ($allFormIds as $formId) {
                // Cari salah satu request dari employee dan dapatkan id nya buat per form_id
                $itemForForm = collect($levelGroup['with_requests'])->first(fn($item) => (string) $item->form_id === (string) $formId);
                if ($itemForForm) {
                    $empId = $itemForForm->employee->employee_id;
                    if (!isset($cachedSuggestedRatings[$empId][$formId])) {
                        $cachedSuggestedRatings[$empId][$formId] = $this->appService->suggestedRating($empId, $formId, $this->period);
                    }
                }
            }

            // ─── 10. Map output $ratingDatas ────────────────────────
            $ratingDatas = collect([$level => $levelGroup])->map(function ($group) use ($user, $period, $allCalibrations, $allRatingValues, $cachedSuggestedRatings, $convertRatingLocal, $ratingAllowedCheckLocal) {
                // Process `with_requests`
                $withRequests = collect($group['with_requests'])->map(function ($data) use ($user, $allCalibrations, $allRatingValues, $cachedSuggestedRatings, $convertRatingLocal, $ratingAllowedCheckLocal) {
                    $employeeId = $data->employee->employee_id;
                    $formId = $data->form_id;

                    $calibrationData = collect();
                    if ($formId && isset($allCalibrations[$employeeId][$formId])) {
                        $calibrationData = collect($allCalibrations[$employeeId][$formId]);
                    }

                    $previousRating = $calibrationData->whereNotNull('rating')->first();
                    $suggestedRatingFloat = round($cachedSuggestedRatings[$employeeId][$formId] ?? 0, 2);

                    $calibratorEntry = $calibrationData->where('approver_id', $user)->first();
                    $data->suggested_rating = $calibratorEntry
                        ? $convertRatingLocal($suggestedRatingFloat, $calibratorEntry->id_calibration_group)
                        : null;

                    $firstCalibration = $calibrationData->first();
                    $data->previous_rating = $previousRating && $firstCalibration
                        ? $convertRatingLocal($previousRating->rating, $firstCalibration->id_calibration_group)
                        : null;

                    $ratingRecord = $allRatingValues->get($employeeId);
                    $data->rating_value = $ratingRecord ? $ratingRecord->rating : null;

                    $data->is_calibrator = $calibrationData->where('approver_id', $user)
                        ->where('status', 'Pending')
                        ->isNotEmpty();

                    $data->rating_allowed = $ratingAllowedCheckLocal($employeeId);
                    $data->rating_incomplete = $calibrationData->whereNull('rating')->count();

                    $userCalibration = $calibrationData->first();
                    if ($userCalibration) {
                        $myCalibration = $calibrationData->where('approver_id', $user)->first();
                        $data->rating_status = $myCalibration ? $myCalibration->status : null;
                        $data->rating_approved_date = Carbon::parse($userCalibration->updated_at)->format('d M Y');
                    }

                    $currentCalibrator = $calibrationData->where('status', 'Pending')->first();
                    $data->current_calibrator = $currentCalibrator && $currentCalibrator->approver
                        ? $currentCalibrator->approver->fullname . ' (' . $currentCalibrator->approver->employee_id . ')'
                        : false;

                    return $data;
                });

                // Process `without_requests`
                $withoutRequests = collect($group['without_requests'])->map(function ($data) use ($user, $allCalibrations, $ratingAllowedCheckLocal) {
                    $employeeId = $data->employee->employee_id;
                    $data->suggested_rating = null;

                    // Gunakan preload data yang sudah dikumpulkan flat
                    $allCalsByEmployee = collect($allCalibrations->get($employeeId, []))->flatten(1);

                    $data->is_calibrator = $allCalsByEmployee
                        ->where('approver_id', $user)
                        ->where('status', 'Pending')
                        ->isNotEmpty();

                    $data->rating_allowed = $ratingAllowedCheckLocal($employeeId);

                    $currentCalibrator = $allCalsByEmployee
                        ->where('status', 'Pending')
                        ->first();

                    $data->current_calibrator = $currentCalibrator && $currentCalibrator->approver
                        ? $currentCalibrator->approver->fullname . ' (' . $currentCalibrator->approver->employee_id . ')'
                        : false;

                    return $data;
                });

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
            // $kpiUnit = KpiUnits::with(['masterCalibration'])->where('employee_id', $this->user)->where('status_aktif', 'T')->where('periode', $this->period)->first();

            $masterRating = MasterRating::select('id_rating_group', 'parameter', 'value', 'min_range', 'max_range')
                ->where('id_rating_group', '30e4e9eb-476f-4914-a123-807958a95260')
                ->get();

            $allowedRating = $masterRating->pluck('parameter')->toArray();

            // Get the ID of the currently authenticated user
            $user = User::where('employee_id', $request->input('user'))->first()->employee_id;

            $userId = $request->input('user') ? $user : Auth::id();
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
            } else {
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
