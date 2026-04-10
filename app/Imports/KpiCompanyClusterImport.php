<?php

namespace App\Imports;

use App\Models\Appraisal;
use App\Models\ApprovalLayer;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use App\Models\Goal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class KPICompanyClusterImport implements ToModel, WithHeadingRow, WithValidation
{
    public array $employeesData = [];
    public int $successCount = 0;
    public int $errorCount = 0;
    public array $detailError = [];
    public string $filePath;
    protected array $validUoms;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;

        $options = json_decode(File::get(base_path('resources/goal.json')), true);
        $this->validUoms = collect($options['UoM'])->flatten()->all();
    }

    public function prepareForValidation($row, $index)
    {
        $row['cluster'] = 'company';
        $row['type'] = strtolower(trim($row['type'] ?? ''));
        return $row;
    }

    public function model(array $row)
    {
        if (empty($row['employee_id'])) return null;

        if (!Employee::where('employee_id', $row['employee_id'])->exists()) {
            $this->errorCount++;
            return null;
        }

        $uom = in_array($row['uom'], $this->validUoms, true) ? $row['uom'] : 'Other';
        $customUom = $uom === 'Other' ? $row['uom'] : null;

        if (!isset($this->employeesData[$row['employee_id']])) {
            $this->employeesData[$row['employee_id']] = [
                'period' => $row['period'],
                'form_data' => [],
            ];
        }

        $typeMap = [
            'higher better' => 'Higher Better',
            'lower better' => 'Lower Better',
            'exact value' => 'Exact Value',
        ];

        $this->employeesData[$row['employee_id']]['form_data'][] = [
            'cluster' => 'company',
            'kpi' => $row['kpi'],
            'target' => $row['target'],
            'uom' => $uom,
            'custom_uom' => $customUom,
            'weightage' => $row['weightage'] * 100,
            'type' => $typeMap[$row['type']] ?? 'Higher Better',
            'description' => $row['achievement'] ?? '',
        ];
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|digits:11',
            'kpi' => 'required',
            'target' => 'required|numeric',
            'uom' => 'required',
            'weightage' => 'required|numeric|max:1',
            'type' => 'required',
            'period' => 'required|numeric',
        ];
    }

    public function saveToDatabase(): void
    {
        foreach ($this->employeesData as $employeeId => $data) {

            DB::beginTransaction();

            try {

                if (Appraisal::where('employee_id', $employeeId)
                    ->where('period', $data['period'])->exists()) {
                    throw new \Exception('Appraisal already exists');
                }

                $existingGoal = DB::table('goals')
                    ->where('employee_id', $employeeId)
                    ->where('category', 'Goals')
                    ->where('period', $data['period'])
                    ->whereNull('deleted_at')
                    ->first();

                if ($existingGoal) {

                    $existingFormData = json_decode($existingGoal->form_data, true) ?? [];

                    $existingCompanyKPI = collect($existingFormData)
                        ->where('cluster', 'company')
                        ->pluck('kpi')
                        ->toArray();

                    $companyData = array_filter($data['form_data'], function ($item) use ($existingCompanyKPI) {
                        return !in_array($item['kpi'], $existingCompanyKPI);
                    });

                    $merged = array_merge(
                        array_filter($existingFormData, fn($item) => ($item['cluster'] ?? '') !== 'company'),
                        $companyData
                    );

                    DB::table('goals')
                        ->where('id', $existingGoal->id)
                        ->update([
                            'form_data' => json_encode(array_values($merged)),
                            'updated_at' => now(),
                        ]);

                } else {

                    $goal = new Goal();
                    $goal->id = Str::uuid();
                    $goal->employee_id = $employeeId;
                    $goal->category = 'Goals';
                    $goal->form_data = json_encode($data['form_data']);
                    $goal->form_status = 'Draft';
                    $goal->period = $data['period'];
                    $goal->save();

                    $empAppraisalId = Employee::where('employee_id', $employeeId)->value('id');

                    $firstLayer = ApprovalLayer::where('employee_id', $employeeId)
                        ->where('layer', 1)
                        ->first();

                    if (!$firstLayer) {
                        throw new \Exception("Approval layer 1 tidak ditemukan untuk employee: $employeeId");
                    }

                    $approvalRequest = new ApprovalRequest();
                    $approvalRequest->form_id = $goal->id;
                    $approvalRequest->category = 'Goals';
                    $approvalRequest->employee_id = $employeeId;
                    $approvalRequest->current_approval_id = $firstLayer->approver_id; /// Approver pertama
                    $approvalRequest->period = $data['period'];
                    $approvalRequest->status = 'Approved';
                    $approvalRequest->created_by = $empAppraisalId;
                    $approvalRequest->save();
                }

                DB::commit();
                $this->successCount++;

            } catch (\Throwable $e) {
                DB::rollBack();
                $this->errorCount++;
                $this->detailError[] = [
                    'employee_id' => $employeeId,
                    'message' => $e->getMessage(),
                ];
            }
        }
    }
}