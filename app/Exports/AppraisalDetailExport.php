<?php

namespace App\Exports;

use App\Models\Appraisal;
use App\Models\AppraisalContributor;
use App\Models\ApprovalRequest;
use App\Models\ApprovalSnapshots;
use App\Models\MasterWeightage;
use App\Services\AppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Facades\Excel;
use stdClass;

class AppraisalDetailExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithChunkReading
{
    protected Collection $data;
    protected array $headers;
    protected AppService $appService;
    protected array $dynamicHeaders = [];
    protected $user;
    protected $period;

    public function __construct(AppService $appService, array $data, array $headers, $user, $period)
    {
        $this->data = collect($data); // Convert array data to a collection
        $this->headers = $headers;
        $this->appService = $appService;
        $this->user = $user;
        $this->period = $period;
    }

    public function collection(): Collection
    {
        $this->dynamicHeaders = []; // Reset dynamic headers for each export

        $expandedData = collect();

        $this->data->chunk(100)->each(function ($chunk) use ($expandedData) {
            $employeeIds = [];
            $formIds = [];
            foreach ($chunk as $row) {
                $employeeId = $row['Employee ID']['dataId'] ?? null;
                $formId = $row['Form ID']['dataId'] ?? null;
                if ($employeeId)
                    $employeeIds[] = $employeeId;
                if ($formId)
                    $formIds[] = $formId;
            }

            $employeeIds = array_unique($employeeIds);
            $formIds = array_unique($formIds);

            $appraisals = Appraisal::with([
                'employee',
                'goal',
                'approvalSnapshots' => function ($query) {
                    $query->latest();
                }
            ])
                ->whereIn('id', $formIds)
                ->get()
                ->keyBy('id');

            $contributors = AppraisalContributor::with([
                'employee'
            ])
                ->whereIn('employee_id', $employeeIds)
                ->where('period', $this->period)
                ->get();

            $contributorsGroupedByEmployee = $contributors->groupBy('employee_id');

            $allAppraisalIdsForSummary = $contributors->pluck('appraisal_id')->unique()->toArray();
            $contributorsForSummary = AppraisalContributor::with(['employee', 'appraisal.goal'])
                ->whereIn('appraisal_id', $allAppraisalIdsForSummary)
                ->get()
                ->groupBy('appraisal_id');

            foreach ($chunk as $row) {
                $employeeId = $row['Employee ID']['dataId'] ?? null;
                $formId = $row['Form ID']['dataId'] ?? null;

                if ($formId && $employeeId) {
                    $appraisal = $appraisals->get($formId);
                    $this->expandRowForSelf($expandedData, $row, $appraisal);

                    if ($contributorsGroupedByEmployee->has($employeeId)) {
                        $conts = $contributorsGroupedByEmployee->get($employeeId);

                        $this->expandRowForContributors($expandedData, $row, $conts, $appraisals);
                        $this->expandRowForSummary($expandedData, $row, $conts, $contributorsForSummary, $appraisals);
                    }
                } else {
                    $expandedData->push($this->createDefaultContributorRow($row));
                }
            }
        });

        return $expandedData;
    }

    private function expandRowForSummary(Collection $expandedData, array $row, Collection $contributors, Collection $contributorsForSummary, Collection $appraisals): void
    {
        $contributor = $contributors->first();

        if ($contributor) {
            $contributorRow = $row;
            $formData = $this->getFormDataSummary($contributor, $contributorsForSummary, $appraisals);
            $contributorRow['Contributor ID'] = ['dataId' => $contributor->employee_id];
            $contributorRow['Contributor Type'] = ['dataId' => 'summary'];
            $this->addFormDataToRow($contributorRow, $formData);

            $expandedData->push($contributorRow);
        }
    }

    private function expandRowForSelf(Collection $expandedData, array $row, ?Appraisal $appraisal): void
    {
        if ($appraisal) {
            $contributorRow = $row;
            $formData = $this->getFormDataSelf($appraisal);
            $contributorRow['Contributor ID'] = ['dataId' => $appraisal->employee_id];
            $contributorRow['Contributor Type'] = ['dataId' => 'self'];
            $this->addFormDataToRow($contributorRow, $formData);

            $expandedData->push($contributorRow);
        }
    }

    private function expandRowForContributors(Collection $expandedData, array $row, Collection $contributors, Collection $appraisals): void
    {
        foreach ($contributors as $contributor) {
            $contributorRow = $row;
            $formData = $this->getFormDataForContributor($contributor, $appraisals);
            $contributorRow['Contributor ID'] = ['dataId' => $contributor->contributor_id];
            $contributorRow['Contributor Type'] = ['dataId' => $contributor->contributor_type];
            $this->addFormDataToRow($contributorRow, $formData);

            $expandedData->push($contributorRow);
        }
    }
    private function addFormDataToRow(array &$contributorRow, array $formData): void
    {
        // Log::info('Starting addFormDataToRow', [
        // 'formData' => $formData,
        // 'contributorRow' => $contributorRow, // Log the current state of contributorRow for debugging
        // ]);

        $isSummary = ($contributorRow['Contributor Type']['dataId'] ?? '') === 'summary';

        if (!$isSummary && !empty($formData['formData']) && is_array($formData['formData'])) {
            foreach ($formData['formData'] as $formGroup) {
                $formName = $formGroup['formName'] ?? 'Unknown';
                foreach ($formGroup as $index => $itemGroup) {
                    if (is_array($itemGroup)) {
                        if ($formName === 'Culture' || $formName === 'Leadership' || $formName === 'Sigap') {
                            $this->processFormGroup($formName, $itemGroup, $contributorRow);
                        } elseif ($formName === 'KPI') {
                            $this->processKPI($formName, $itemGroup, $contributorRow, $index);
                        }
                    }
                }
            }
        }

        // Ensure score columns always exist with safe fallbacks so Sigap and other scores appear
        $contributorRow['KPI Score'] = ['dataId' => (isset($formData['totalKpiScore']) && $formData['totalKpiScore'] !== null) ? round($formData['totalKpiScore'], 2) : '-'];
        $contributorRow['Culture Score'] = ['dataId' => (isset($formData['totalCultureScore']) && $formData['totalCultureScore'] !== null) ? round($formData['totalCultureScore'], 2) : '-'];
        $contributorRow['Leadership Score'] = ['dataId' => (isset($formData['totalLeadershipScore']) && $formData['totalLeadershipScore'] !== null) ? round($formData['totalLeadershipScore'], 2) : '-'];
        $contributorRow['Sigap Score'] = ['dataId' => (isset($formData['sigapScore']) && $formData['sigapScore'] !== null) ? round($formData['sigapScore'], 2) : '-'];
        $contributorRow['Total Score'] = ['dataId' => (isset($formData['totalScore']) && $formData['totalScore'] !== null) ? round($formData['totalScore'], 2) : '-'];
    }

    /**
     * Process the individual form group items and populate headers.
     */
    private function processFormGroup(string $formName, array $itemGroup, array &$contributorRow): void
    {
        if (strtolower($formName) === 'sigap') {
            $this->processSigap($formName, $itemGroup, $contributorRow);
        } else {
            $this->processCultureOrLeadership($formName, $itemGroup, $contributorRow);
        }
    }

    private function processCultureOrLeadership(string $formName, array $itemGroup, array &$contributorRow): void
    {
        $title = $itemGroup['title'] ?? 'Unknown Title';

        foreach ($itemGroup as $subIndex => $item) {
            if (is_array($item) && isset($item['formItem'], $item['score'])) {
                $subNumber = $subIndex + 1;
                $header = strtolower(trim("{$formName}_{$title}_{$subNumber}"));
                $this->captureDynamicHeader($header);
                $contributorRow[$header] = ['dataId' => strip_tags((string) $item['formItem']) . "|" . $item['score']];
            }
        }
    }

    private function processSigap(string $formName, array $itemGroup, array &$contributorRow): void
    {
        // Log::info('Processing Sigap form group', [
        //     'formName' => $formName,
        //     'itemGroup' => $itemGroup, // Log the entire item group for debugging
        //     'contributorRow' => $contributorRow, // Log the current state of contributorRow for debugging

        // ]);
        // title and items definitions
        if (empty($itemGroup['title'])) {
            // Skip processing when there's no title
            return;
        }
        $title = $itemGroup['title'];
        $items = is_array($itemGroup['items'] ?? null) ? $itemGroup['items'] : [];

        // extract score from possible locations
        $score = null;
        if (isset($itemGroup[0]['score'])) {
            $score = $itemGroup[0]['score'];
        } elseif (isset($itemGroup['score'])) {
            $score = $itemGroup['score'];
        } elseif (isset($itemGroup['value'])) {
            $score = $itemGroup['value'];
        }

        // determine the descriptive text for the selected score
        $formItemText = '';
        if ($score !== null) {
            $scoreIndex = (string) (int) $score; // normalize index
            if (isset($items[$scoreIndex])) {
                $itemDesc = $items[$scoreIndex];
                if (is_array($itemDesc)) {
                    $formItemText = $itemDesc['desc_eng'] ?? $itemDesc['desc_idn'] ?? json_encode($itemDesc);
                } else {
                    $formItemText = (string) $itemDesc;
                }
            }
        }

        // fallback: if items not present but itemGroup contains direct formItem
        if ($formItemText === '' && isset($itemGroup[0]['formItem'])) {
            $formItemText = (string) $itemGroup[0]['formItem'];
        } elseif ($formItemText === '' && isset($itemGroup['formItem'])) {
            $formItemText = (string) $itemGroup['formItem'];
        }

        $header = strtolower(trim("{$formName}_{$title}"));
        $this->captureDynamicHeader($header);
        $contributorRow[$header] = ['dataId' => strip_tags($formItemText) . "|" . ($score ?? '')];
    }

    private function processKPI(string $formName, array $itemGroup, array &$contributorRow, int $index): void
    {
        $maxKpi = 30;
        $index = min($index, $maxKpi - 1); // Ensure index stays within 0-9

        // Normalize / safeguard keys to avoid "Undefined array key" errors
        $itemGroupSafe = [
            'kpi' => $itemGroup['kpi'] ?? null,
            'target' => $itemGroup['target'] ?? null,
            'achievement' => $itemGroup['achievement'] ?? null,
            'uom' => $itemGroup['uom'] ?? null,
            'weightage' => $itemGroup['weightage'] ?? null,
            'type' => $itemGroup['type'] ?? null,
            'custom_uom' => $itemGroup['custom_uom'] ?? null,
            'percentage' => $itemGroup['percentage'] ?? null,
            'conversion' => $itemGroup['conversion'] ?? null,
            'final_score' => $itemGroup['final_score'] ?? null,
        ];

        // Generate headers for ALL 10 KPI positions (1-10)
        for ($kpiNumber = 1; $kpiNumber <= $maxKpi; $kpiNumber++) {
            foreach ($itemGroup as $subKey => $value) {
                $kpiKey = strtolower(trim("{$formName}_{$subKey}_{$kpiNumber}"));
                $this->captureDynamicHeader($kpiKey);
            }
        }

        // Process current KPI's data
        foreach ($itemGroup as $subKey => $value) {
            $subNumber = $index + 1; // Convert to 1-based index
            $kpiKey = strtolower(trim("{$formName}_{$subKey}_{$subNumber}"));
            $contributorRow[$kpiKey] = ['dataId' => $value];
        }
    }

    // Helper function to capture unique dynamic headers
    private function captureDynamicHeader(string $header): void
    {
        if (!isset($this->dynamicHeaders[$header])) {
            $this->dynamicHeaders[$header] = $header;
        }
    }

    private function getFormDataForContributor(AppraisalContributor $contributor, Collection $appraisals): array
    {
        $appraisal = $appraisals->get($contributor->appraisal_id);

        // If the underlying appraisal is still a Draft, skip heavy calculations
        if ($appraisal && (($appraisal->form_status ?? null) === 'Draft' || ($contributor->status ?? null) === 'Draft')) {
            return [
                'formData' => [],
                'totalKpiScore' => null,
                'totalCultureScore' => null,
                'totalLeadershipScore' => null,
                'sigapScore' => null,
                'totalScore' => null,
            ];
        }

        // Prepare the goal and appraisal data
        $goalData = json_decode($appraisal->goal->form_data ?? '[]', true);

        $appraisalData = json_decode($contributor->form_data ?? '[]', true);
        $appraisalData['contributor_type'] = $contributor->contributor_type;
        $appraisalData = array($appraisalData);

        $employeeData = $contributor->employee;

        $formGroupContent = $this->appService->formGroupAppraisal($contributor->employee_id, 'Appraisal Form', $contributor->period);
        $appraisalForm = $formGroupContent ?: ['data' => ['formData' => []]];

        if (!$formGroupContent || !isset($formGroupContent['data'])) {
            $appraisalForm = ['data' => ['formData' => [], 'form_appraisals' => []]];
        } else {
            $appraisalForm = $formGroupContent;
        }

        // culture & leadership BI items
        $formAppraisals = data_get($appraisalForm, 'data.form_appraisals', []);
        $cultureData = $this->appService->getDataByName($formAppraisals, 'Culture') ?? [];
        $leadershipData = $this->appService->getDataByName($formAppraisals, 'Leadership') ?? [];
        $sigapData = $this->appService->getDataByName($formAppraisals, 'Sigap') ?? [];

        $jobLevel = $employeeData->job_level;

        $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $contributor->period)->first();

        $weightageContent = json_decode($weightageData->form_data, true);

        // if ($this->user->hasRole('superadmin')) {
        //     // for non percentage by 360 data BI items
        //     $result = $this->appService->appraisalSummaryWithout360Calculation($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);
        // } else {
        $result = $this->appService->appraisalSummary($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);
        // }

        // Log::info('Calculated appraisal summary for contributor', [
        //     'employee_id' => $employeeData->employee_id,
        //     'contributor_type' => $contributor->contributor_type,
        //     'Result' => $result,
        // ]);

        $formData = $this->appService->combineFormData($appraisalData[0], $goalData, $contributor->contributor_type, $employeeData, $contributor->period);

        // Log::info('Debug Export Data', [
        //     'data' => $this->user, // Log only the first 10 rows
        // ]);

        if (isset($formData['formData']) && is_array($formData['formData'])) {
            foreach ($formData['formData'] as &$form) {
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
        }

        return $formData;
    }

    private function getFormDataSelf(Appraisal $appraisal): array
    {
        $latestSnapshot = null;
        if ($appraisal->relationLoaded('approvalSnapshots') && $appraisal->approvalSnapshots) {
            $snap = $appraisal->approvalSnapshots;
            $isValid = true;

            if (!$snap->form_data) {
                $isValid = false;
            } else {
                $decoded = json_decode($snap->form_data, true);
                if (is_array($decoded) && isset($decoded['formGroupName']) && $decoded['formGroupName'] === 'Appraisal Form 360') {
                    $isValid = false;
                }
            }

            if ($isValid) {
                $latestSnapshot = $snap;
            }
        }

        $goalData = $appraisal->goal ? json_decode($appraisal->goal->form_data, true) : [];
        $appraisalData = $latestSnapshot ? json_decode($latestSnapshot->form_data, true) : [];

        $appraisalData['contributor_type'] = "employee";

        $appraisalData = array($appraisalData);

        $employeeData = $appraisal->employee;

        // Setelah data digabungkan, gunakan combineFormData untuk setiap jenis kontributor

        $formGroupContent = $this->appService->formGroupAppraisal($appraisal->employee_id, 'Appraisal Form', $appraisal->period);

        if (!$formGroupContent || !isset($formGroupContent['data'])) {
            $appraisalForm = ['data' => ['formData' => [], 'form_appraisals' => []]];
        } else {
            $appraisalForm = $formGroupContent;
        }

        $formAppraisals = data_get($appraisalForm, 'data.form_appraisals', []);
        $cultureData = $this->appService->getDataByName($formAppraisals, 'Culture') ?? [];
        $leadershipData = $this->appService->getDataByName($formAppraisals, 'Leadership') ?? [];
        $sigapData = $this->appService->getDataByName($formAppraisals, 'Sigap') ?? [];

        $jobLevel = $employeeData->job_level;

        $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $appraisal->period)->first();

        $weightageContent = json_decode($weightageData->form_data, true);

        // Use the standard appraisalSummary to avoid the without-360 code path causing string-offset errors
        $result = $this->appService->appraisalSummary($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);

        $formData = $this->appService->combineFormData($appraisalData[0], $goalData, 'employee', $employeeData, $appraisal->period);

        // Log::info('Calculated appraisal summary for self', [
        //     'formData' => $formData,
        // ]);

        if (isset($formData['formData']) && is_array($formData['formData'])) {
            foreach ($formData['formData'] as &$form) {
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
        }

        return $formData;
    }

    private function getFormDataSummary(AppraisalContributor $contributor, Collection $contributorsForSummary, Collection $appraisals): array
    {
        $datas = $contributorsForSummary->get($contributor->appraisal_id) ?? collect();
        $appraisal = $appraisals->get($contributor->appraisal_id);

        if ($datas->isEmpty() || !$appraisal) {
            return [
                'formData' => [],
                'totalKpiScore' => null,
                'totalCultureScore' => null,
                'totalLeadershipScore' => null,
                'sigapScore' => null,
                'totalScore' => null,
            ];
        }

        $firstEmployee = $datas->first()->employee;

        $checkSnapshot = null;
        if ($appraisal->relationLoaded('approvalSnapshots') && $appraisal->approvalSnapshots) {
            $checkSnapshot = $appraisal->approvalSnapshots;
        }

        $employeeForm = $checkSnapshot;

        $data = [];
        $appraisalDataCollection = [];
        $goalDataCollection = [];

        $formGroupContent = $this->appService->formGroupAppraisal($datas->first()->employee_id, 'Appraisal Form', $contributor->period);

        if (!$formGroupContent || !isset($formGroupContent['data'])) {
            $appraisalForm = ['data' => ['formData' => [], 'form_appraisals' => []]];
        } else {
            $appraisalForm = $formGroupContent;
        }

        $formAppraisals = data_get($appraisalForm, 'data.form_appraisals', []);
        $cultureData = $this->appService->getDataByName($formAppraisals, 'Culture') ?? [];
        $leadershipData = $this->appService->getDataByName($formAppraisals, 'Leadership') ?? [];
        $sigapData = $this->appService->getDataByName($formAppraisals, 'Sigap') ?? [];


        if ($employeeForm) {

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

        $jobLevel = $firstEmployee->job_level;

        $cacheKeyWeightage = "{$firstEmployee->group_company}_{$contributor->period}";
        if (!isset($this->appService->masterWeightageCache[$cacheKeyWeightage])) {
            $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $firstEmployee->group_company . '%')->where('period', $contributor->period)->first();
            $this->appService->masterWeightageCache[$cacheKeyWeightage] = $weightageData ? json_decode($weightageData->form_data, true) : null;
        }

        $weightageContent = $this->appService->masterWeightageCache[$cacheKeyWeightage];

        $result = $this->appService->appraisalSummary($weightageContent, $formData, $firstEmployee->employee_id, $jobLevel);

        $formData = $this->appService->combineSummaryFormData($result, $goalDataCollection, $firstEmployee, $contributor->period);

        if (isset($formData['formData']) && is_array($formData['formData'])) {
            foreach ($formData['formData'] as &$form) {
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
                // if ($form['formName'] === 'Sigap') {
                //     foreach ($sigapData as $index => $sigapItem) {
                //         foreach ($sigapItem['items'] as $itemIndex => $item) {
                //             if (isset($form[$index][$itemIndex])) {
                //                 $form[$index][$itemIndex] = [
                //                     'formItem' => $item,
                //                     'score' => $form[$index][$itemIndex]['score']
                //                 ];
                //             }
                //         }
                //         $form[$index]['title'] = $sigapItem['title'];
                //         $form[$index]['items'] = $sigapItem['items'];
                //     }
                // }
            }
        }

        return $formData;
    }

    private function createDefaultContributorRow(array $row): array
    {
        $row['Contributor ID'] = ['dataId' => '-'];
        $row['Contributor Type'] = ['dataId' => '-'];
        $row['KPI Score'] = ['dataId' => '-'];
        $row['Culture Score'] = ['dataId' => '-'];
        $row['Leadership Score'] = ['dataId' => '-'];
        $row['Sigap Score'] = ['dataId' => '-'];
        $row['Total Score'] = ['dataId' => '-'];
        return $row;
    }

    protected $headersCached = false;

    public function headings(): array
    {
        if (!$this->headersCached) {
            $this->collection();
            $this->headersCached = true;
        }

        if (empty($this->dynamicHeaders)) {
            // Populate collection to ensure dynamic headers are captured
            $this->collection();
        }

        $extendedHeaders = $this->headers;

        foreach (['Contributor ID', 'Contributor Type', 'KPI Score', 'Culture Score', 'Leadership Score', 'Sigap Score', 'Total Score'] as $header) {
            if (!in_array($header, $extendedHeaders)) {
                $extendedHeaders[] = $header;
            }
        }

        // Separate headers by category
        $kpiHeaders = [];
        $cultureHeaders = [];
        $leadershipHeaders = [];
        $sigapHeaders = [];

        foreach ($this->dynamicHeaders as $header) {
            if (strpos($header, 'kpi_') === 0) {
                $kpiHeaders[] = $header;
            } elseif (strpos($header, 'culture_') === 0) {
                $cultureHeaders[] = $header;
            } elseif (strpos($header, 'leadership_') === 0) {
                $leadershipHeaders[] = $header;
            } elseif (strpos($header, 'sigap_') === 0) {
                $sigapHeaders[] = $header;
            }
        }

        // Sort KPI headers by numeric index
        usort($kpiHeaders, function ($a, $b) {
            // Extract the numeric part after 'kpi_' and before the next '_'
            preg_match('/kpi_(\d+)_/', $a, $aMatches);
            preg_match('/kpi_(\d+)_/', $b, $bMatches);
            $aIndex = isset($aMatches[1]) ? (int) $aMatches[1] : 0;
            $bIndex = isset($bMatches[1]) ? (int) $bMatches[1] : 0;

            return $aIndex <=> $bIndex;
        });

        // Sort Culture, Leadership and Sigap headers alphabetically
        sort($cultureHeaders);
        sort($leadershipHeaders);
        sort($sigapHeaders);

        // Merge all sorted headers back in the desired order
        $sortedDynamicHeaders = array_merge($kpiHeaders, $cultureHeaders, $leadershipHeaders, $sigapHeaders);

        // Add sorted dynamic headers to the extended headers
        foreach ($sortedDynamicHeaders as $header) {
            if (!in_array($header, $extendedHeaders)) {
                $extendedHeaders[] = $header;
            }
        }

        // Log::info("Headings returned:", $extendedHeaders);
        return $extendedHeaders;
    }


    public function map($row): array
    {
        $data = [];
        foreach ($this->headings() as $header) {
            $data[] = $row[$header]['dataId'] ?? '';
        }
        return $data;
    }

    public function chunkSize(): int
    {
        return 1000; // Adjust based on your data size
    }
}