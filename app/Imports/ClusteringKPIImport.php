<?php

namespace App\Imports;

use App\Models\Appraisal;
use App\Models\ApprovalLayer;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class ClusteringKPIImport implements
    ToModel,
    WithHeadingRow,
    WithValidation
{
    public int $successCount = 0;
    public int $errorCount = 0;

    public string $filePath;

    /** @var array<string, array> */
    public array $employeesData = [];

    public array $detailError = [];

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Normalize BEFORE validation
     */
    public function prepareForValidation($row, $index)
    {
        if (isset($row['cluster'])) {
            $row['cluster'] = strtolower(trim($row['cluster']));
        }

        if (isset($row['type'])) {
            $row['type'] = strtolower(trim($row['type']));
        }

        return $row;
    }

    /**
     * Laravel-Excel per row handler
     */
    public function model(array $row)
    {
        Log::info('IMPORT ROW', $row);

        // 🔴 Hard stop if employee_id missing
        if (empty($row['employee_id'])) {
            $this->errorCount++;
            $this->detailError[] = [
                'employee_id' => null,
                'message' => 'Employee ID is required',
            ];
            return null;
        }

        $employeeId = $row['employee_id'];

        // 🔒 Ensure employee exists
        if (!Employee::where('employee_id', $employeeId)->exists()) {
            $this->errorCount++;
            $this->detailError[] = [
                'employee_id' => $employeeId,
                'message' => 'Employee not found',
            ];
            return null;
        }

        // 📦 Load UoM options
        $options = json_decode(
            File::get(base_path('resources/goal.json')),
            true
        );

        $validUoms = collect($options['UoM'])->flatten()->all();

        $uom = in_array($row['uom'], $validUoms, true)
            ? $row['uom']
            : 'Other';

        $customUom = $uom === 'Other' ? $row['uom'] : null;

        // 🧱 Group by employee
        if (!isset($this->employeesData[$employeeId])) {
            $this->employeesData[$employeeId] = [
                'category' => 'Goals',
                'period' => $row['period'],
                'current_approval_id' => 'admin',
                'form_data' => [],
            ];
        }

        $this->employeesData[$employeeId]['form_data'][] = [
            'cluster' => $row['cluster'],
            'kpi' => $row['kpi'],
            'target' => $row['target'],
            'uom' => $uom,
            'custom_uom' => $customUom,
            'weightage' => $row['weightage'] * 100,
            'type' => $row['type'],
            'description' => $row['achievement'] ?? '',
        ];
    }

    /**
     * Validation rules (snake_case ONLY)
     */
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
            'cluster' => 'required|in:company,division,personal',
            'achievement' => 'nullable',
        ];
    }

    /**
     * Persist grouped data
     */
    public function saveToDatabase(): void
    {
        ksort($this->employeesData);

        foreach ($this->employeesData as $employeeId => $data) {

            DB::beginTransaction();

            try {
                if (
                    Appraisal::where('employee_id', $employeeId)
                        ->where('period', $data['period'])
                        ->exists()
                ) {
                    throw new \Exception('Appraisal already exists');
                }

                $formId = (string) Str::uuid();

                DB::table('goals')
                    ->where('employee_id', $employeeId)
                    ->where('category', $data['category'])
                    ->where('period', $data['period'])
                    ->update(['deleted_at' => now()]);

                DB::table('goals')->insert([
                    'id' => $formId,
                    'employee_id' => $employeeId,
                    'category' => $data['category'],
                    'form_data' => json_encode($data['form_data']),
                    'form_status' => 'Approved',
                    'period' => $data['period'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $empAppraisalId = EmployeeAppraisal::where('employee_id', $employeeId)
                    ->value('id');

                DB::table('approval_requests')->insert([
                    'form_id' => $formId,
                    'category' => 'Goals',
                    'employee_id' => $employeeId,
                    'current_approval_id' => 'admin',
                    'status' => 'Approved',
                    'messages' => 'import clustering KPI',
                    'period' => $data['period'],
                    'created_by' => $empAppraisalId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

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

    /**
     * Save import summary
     */
    public function saveTransaction(): void
    {
        DB::table('goals_import_transactions')->insert([
            'success' => $this->successCount,
            'error' => $this->errorCount,
            'detail_error' => json_encode($this->detailError),
            'file_uploads' => str_replace('public/', '', $this->filePath),
            'submit_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
