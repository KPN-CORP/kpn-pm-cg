<?php

namespace App\Imports;

use App\Models\Appraisal;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
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

                $formId = (string) Str::uuid();

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

                    DB::table('goals')->insert([
                        'id' => $formId,
                        'employee_id' => $employeeId,
                        'category' => 'Goals',
                        'form_data' => json_encode($data['form_data']),
                        'form_status' => 'Approved',
                        'period' => $data['period'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $empAppraisalId = EmployeeAppraisal::where('employee_id', $employeeId)->value('id');

                    DB::table('approval_requests')->insert([
                        'form_id' => $formId,
                        'category' => 'Goals',
                        'employee_id' => $employeeId,
                        'current_approval_id' => 'admin',
                        'status' => 'Approved',
                        'messages' => 'import company KPI',
                        'period' => $data['period'],
                        'created_by' => $empAppraisalId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
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