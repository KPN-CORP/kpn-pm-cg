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

        $contributorsGroupedByEmployee = AppraisalContributor::with([
            'employee' => function ($query) {
                $query->select('employee_id', 'fullname', 'gender', 'email', 'job_level', 'group_company', 'designation_name', 'company_name', 'contribution_level_code'); // Adjust fields as needed
            }
        ])
        ->where('period', $this->period)
        ->get()
        ->groupBy('employee_id');

        $employeeAppraisalById = Appraisal::with([
            'employee' => function ($query) {
                $query->select('employee_id', 'fullname', 'gender', 'email', 'job_level', 'group_company', 'designation_name', 'company_name', 'contribution_level_code'); // Adjust fields as needed
            }
        ])
        ->where('period', $this->period)
        ->get()
        ->groupBy('id');

        $expandedData = collect();

        $this->data->chunk(100)->each(function ($chunk) use ($expandedData, $contributorsGroupedByEmployee, $employeeAppraisalById) {
            foreach ($chunk as $row) {
                $employeeId = $row['Employee ID']['dataId'] ?? null;
                $formId = $row['Form ID']['dataId'] ?? null;

                
                if ($formId && $employeeId) {
                    // Log::info('Preprocessing data export', [
                    //     'data_preview' => $employeeAppraisalById->get($formId),
                    // ]);
                    $this->expandRowForSelf($expandedData, $row, $employeeAppraisalById->get($formId));

                    if ($contributorsGroupedByEmployee->has($employeeId)) {
                        $this->expandRowForContributors($expandedData, $row, $contributorsGroupedByEmployee->get($employeeId));
                        $this->expandRowForSummary($expandedData, $row, $contributorsGroupedByEmployee->get($employeeId));
    
                    }
                } else {
                    $expandedData->push($this->createDefaultContributorRow($row));
                }

            }
        });

        return $expandedData;
    }

    private function expandRowForSummary(Collection $expandedData, array $row, Collection $contributors): void
    {
        $contributor = $contributors->first();

        if ($contributor) {
            $contributorRow = $row;
            $formData = $this->getFormDataSummary($contributor);
            $contributorRow['Contributor ID'] = ['dataId' => $contributor->employee_id];
            $contributorRow['Contributor Type'] = ['dataId' => 'summary'];
            $this->addFormDataToRow($contributorRow, $formData);

            // Add the processed row to expandedData
            $expandedData->push($contributorRow);
        }
    }

    private function expandRowForSelf(Collection $expandedData, array $row, Collection $contributors): void
    {
        
        $contributor = $contributors->first();

        if ($contributor) {
            $contributorRow = $row;
            $formData = $this->getFormDataSelf($contributor);
            $contributorRow['Contributor ID'] = ['dataId' => $contributor->employee_id];
            $contributorRow['Contributor Type'] = ['dataId' => 'self'];
            $this->addFormDataToRow($contributorRow, $formData);

            // Add the processed row to expandedData
            $expandedData->push($contributorRow);
        }
    }

    private function expandRowForContributors(Collection $expandedData, array $row, Collection $contributors): void
    {
        foreach ($contributors as $contributor) {
            $contributorRow = $row;
            $formData = $this->getFormDataForContributor($contributor);
            $contributorRow['Contributor ID'] = ['dataId' => $contributor->contributor_id];
            $contributorRow['Contributor Type'] = ['dataId' => $contributor->contributor_type];
            $this->addFormDataToRow($contributorRow, $formData);
            $expandedData->push($contributorRow);
        }
    }
    private function addFormDataToRow(array &$contributorRow, array $formData): void
    {
        if (isset($formData['formData'])) {
            foreach ($formData['formData'] as $formGroup) {
                $formName = $formGroup['formName'] ?? 'Unknown';
                foreach ($formGroup as $index => $itemGroup) {
                    // Log::info('Preprocessing data to temp table', [
                    //     'data_preview' => $index, // Log only the first 10 rows
                    // ]);
                            if (is_array($itemGroup)) {
                                if ($formName === 'Culture' || $formName === 'Leadership' || $formName === 'Sigap') {
                                    $this->processFormGroup($formName, $itemGroup, $contributorRow);
                                } elseif ($formName === 'KPI') {
                                    $this->processKPI($formName, $itemGroup, $contributorRow, $index);
                                }
                    }
                }
            }
                    $contributorRow['KPI Score'] = ['dataId' => round($formData['totalKpiScore'], 2) ?? '-'];
                    $contributorRow['Culture Score'] = ['dataId' => round($formData['totalCultureScore'], 2) ?? '-'];
                    $contributorRow['Leadership Score'] = ['dataId' => round($formData['totalLeadershipScore'], 2) ?? '-'];
                    $contributorRow['Sigap Score'] = ['dataId' => round($formData['totalSigapScore'] ?? 0, 2) ?? '-'];
                    $contributorRow['Total Score'] = ['dataId' => round($formData['totalScore'], 2) ?? '-'];
        }
    }

    /**
     * Process the individual form group items and populate headers.
     */
    private function processFormGroup(string $formName, array $itemGroup, array &$contributorRow): void
    {
        $this->processCultureOrLeadership($formName, $itemGroup, $contributorRow);
    }

    private function processCultureOrLeadership(string $formName, array $itemGroup, array &$contributorRow): void
    {
        $title = $itemGroup['title'] ?? 'Unknown Title';

        foreach ($itemGroup as $subIndex => $item) {
            if (is_array($item) && isset($item['formItem'], $item['score'])) {
                $subNumber = $subIndex + 1;
                $header = strtolower(trim("{$formName}_{$title}_{$subNumber}"));
                $this->captureDynamicHeader($header);
                $contributorRow[$header] = ['dataId' => strip_tags($item['formItem']) . "|" . $item['score']];
            }
        }
    }

    private function processKPI(string $formName, array $itemGroup, array &$contributorRow, int $index): void
    {
        $maxKpi = 10;
        $index = min($index, $maxKpi - 1); // Ensure index stays within 0-9

        $itemGroup = [
            "kpi" => $itemGroup["kpi"],
            "target" => $itemGroup["target"],
            "achievement" => $itemGroup["achievement"],
            "uom" => $itemGroup["uom"],
            "weightage" => $itemGroup["weightage"],
            "type" => $itemGroup["type"],
            "custom_uom" => $itemGroup["custom_uom"],
            "percentage" => $itemGroup["percentage"],
            "conversion" => $itemGroup["conversion"],
            "final_score" => $itemGroup["final_score"],
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

    private function getFormDataForContributor(AppraisalContributor $contributor): array
    {
        $appraisal = Appraisal::with(['goal'])->where('id', $contributor->appraisal_id)->first();

        // Prepare the goal and appraisal data
        $goalData = json_decode($appraisal->goal->form_data ?? '[]', true);

        $appraisalData = json_decode($contributor->form_data ?? '[]', true);
        $appraisalData['contributor_type'] = $contributor->contributor_type;
        $appraisalData = array($appraisalData);

        $employeeData = $contributor->employee;

        $formGroupContent = $this->appService->formGroupAppraisal($contributor->employee_id, 'Appraisal Form', $contributor->period);
        $appraisalForm = $formGroupContent ?: ['data' => ['formData' => []]];

        if (!$formGroupContent) {
            $appraisalForm = ['data' => ['formData' => []]];
        } else {
            $appraisalForm = $formGroupContent;
        }

        // culture & leadership BI items
        $cultureData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Culture') ?? [];
        $leadershipData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Leadership') ?? [];
        $sigapData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Sigap') ?? [];

        $jobLevel = $employeeData->job_level;

        $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $contributor->period)->first();

        $weightageContent = json_decode($weightageData->form_data, true);

        if ($this->user->hasRole('superadmin')) {
            // for non percentage by 360 data BI items
            $result = $this->appService->appraisalSummaryWithout360Calculation($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);
        } else {
            $result = $this->appService->appraisalSummary($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);
        }

        $formData = $this->appService->combineFormData($result['calculated_data'][0], $goalData, $contributor->contributor_type, $employeeData, $contributor->period);

        // Log::info('Debug Export Data', [
        //     'data' => $this->user, // Log only the first 10 rows
        // ]);

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
                }
            }
        }

        return $formData;
    }

    private function getFormDataSelf(Appraisal $contributor): array
    {
        $datas = Appraisal::with([
            'employee',
            'approvalSnapshots' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }
        ])->where('id', $contributor->id)->get();

        if(!$datas->first()->approvalSnapshots){
            Log::info('Debug Snapshots Data', [
                'data' => $datas->first()->employee_id, // Log only the first 10 rows
            ]);
        }

        $goalData = $datas->isNotEmpty() ? json_decode($datas->first()->goal->form_data, true) : [];
        $appraisalData = $datas->isNotEmpty() ? json_decode($datas->first()->approvalSnapshots->form_data, true) : [];

        $appraisalData['contributor_type'] = "employee";

        $appraisalData = array($appraisalData);

        $data = $datas->first();
        $employeeData = $data ? $data->employee : null;

        // Setelah data digabungkan, gunakan combineFormData untuk setiap jenis kontributor

        $formGroupContent = $this->appService->formGroupAppraisal($datas->first()->employee_id, 'Appraisal Form', $contributor->period);

        if (!$formGroupContent) {
            $appraisalForm = ['data' => ['formData' => []]];
        } else {
            $appraisalForm = $formGroupContent;
        }

        $cultureData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Culture') ?? [];
        $leadershipData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Leadership') ?? [];
        $sigapData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Sigap') ?? [];

        $jobLevel = $employeeData->job_level;

        $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $contributor->period)->first();

        $weightageContent = json_decode($weightageData->form_data, true);

        if ($this->user->hasRole('superadmin')) {
            // for non percentage by 360 data BI items
            $result = $this->appService->appraisalSummaryWithout360Calculation($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);
        } else {
            $result = $this->appService->appraisalSummary($weightageContent, $appraisalData, $employeeData->employee_id, $jobLevel);
        }

        $formData = $this->appService->combineFormData($result['calculated_data'][0], $goalData, 'employee', $employeeData, $datas->first()->period);

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

        return $formData;
    }

    private function getFormDataSummary(AppraisalContributor $contributor): array
    {
        $datasQuery = AppraisalContributor::with(['employee'])->where('appraisal_id', $contributor->appraisal_id);
        $datas = $datasQuery->get();

        $checkSnapshot = ApprovalSnapshots::where('form_id', $contributor->appraisal_id)->where('created_by', $datas->first()->employee->id)
            ->orderBy('created_at', 'desc');

        // Check if `datas->first()->employee->id` exists
        if ($checkSnapshot) {
            $query = $checkSnapshot;
        } else {
            $query = ApprovalSnapshots::where('form_id', $contributor->appraisal_id)
                ->orderBy('created_at', 'asc');
        }

        $employeeForm = $query->first();

        $data = [];
        $appraisalDataCollection = [];
        $goalDataCollection = [];

        $formGroupContent = $this->appService->formGroupAppraisal($datas->first()->employee_id, 'Appraisal Form', $contributor->period);

        if (!$formGroupContent) {
            $appraisalForm = ['data' => ['formData' => []]];
        } else {
            $appraisalForm = $formGroupContent;
        }

        $cultureData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Culture') ?? [];
        $leadershipData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Leadership') ?? [];
        $sigapData = $this->appService->getDataByName($appraisalForm['data']['form_appraisals'], 'Sigap') ?? [];


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

        $jobLevel = $employeeData->job_level;

        $weightageData = MasterWeightage::where('group_company', 'LIKE', '%' . $employeeData->group_company . '%')->where('period', $request->period)->first();

        $weightageContent = json_decode($weightageData->form_data, true);

        $result = $this->appService->appraisalSummary($weightageContent, $formData, $employeeData->employee_id, $jobLevel);

        // $formData = $this->appService->combineFormData($result['summary'], $goalData, $result['summary']['contributor_type'], $employeeData, $request->period);

        $formData = $this->appService->combineSummaryFormData($result, $goalData, $employeeData, $request->period);

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