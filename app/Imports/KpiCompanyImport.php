<?php

namespace App\Imports;

use App\Models\KpiCompany;
use App\Models\Appraisal;
use App\Models\ApprovalSnapshots;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Crypt;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class KpiCompanyImport implements ToCollection, WithHeadingRow, WithValidation
{
    private $success = 0;
    private $error = 0;
    private $errors = [];
    private $period;
    private $appraisalPointers = []; // track pointer per employee during import

    public function __construct($period)
    {
        $this->period = $period;
    }

    /**
     * @param Collection $collection
     */

    private $kpiBuffer = [];

    public function collection(Collection $collection)
    {
        // dd($collection);
        foreach ($collection as $index => $row) {
            try {
                // Skip completely empty rows
                if ((string) trim($row['employee_id'] ?? '') === '' && (string) trim($row['employee_name'] ?? '') === '') {
                    continue;
                }

                // Mandatory columns check (all required)
                $required = ['employee_id','employee_name','kpi', 'target','uom','weightage','type','achievement','cluster'];
                foreach ($required as $col) {
                    if (!isset($row[$col]) || (string) trim($row[$col]) === '') {
                        $this->error++;
                        $this->errors[] = [
                            'row' => $index + 1,
                            'employee_id' => $row['employee_id'] ?? 'N/A',
                            'message' => "Missing required column: {$col}",
                        ];
                        // skip this row
                        continue 2;
                    }
                }

                // Prepare form data for KpiCompany
                $this->kpiBuffer[$row['employee_id']][] = [
                    'kpi'         => $row['kpi'],
                    'target'      => $row['target'],
                    'uom'         => $row['uom'],
                    'weightage'   => $row['weightage'],
                    'type'        => $row['type'],
                    'custom_uom'  => null,
                    'achievement' => $row['achievement'],
                ];


                // Create KPI Company record
                foreach ($this->kpiBuffer as $employeeId => $kpis) {

                    $kpi = KpiCompany::where('employee_id', $employeeId)
                        ->where('period', $this->period)
                        ->first();

                    if ($kpi) {
                        // UPDATE
                        $kpi->update([
                            'form_data' => json_encode($kpis),
                        ]);
                    } else {
                        $newKpiCompany = new KpiCompany();
                        $newKpiCompany->id = (string) Str::uuid();
                        $newKpiCompany->employee_id = $employeeId;
                        $newKpiCompany->period = $this->period;
                        $newKpiCompany->form_data = json_encode($kpis);
                        $newKpiCompany->save();
                        // CREATE
                    }
                }



                // Check and update Appraisal.form_data if appraisal exists for employee + period
                $appraisal = Appraisal::where('employee_id', $row['employee_id'])
                    ->where('period', $this->period)
                    ->first();

                $user = User::where('employee_id', $row['employee_id'])->first();

                if ($appraisal) {
                    $raw = $appraisal->form_data;
                    $wasEncrypted = false;
                    $decoded = json_decode($raw, true);
                    
                    if (!is_array($decoded)) {
                        // try decrypt then decode
                        try {
                            $decrypted = Crypt::decryptString($raw);
                            $decoded = json_decode($decrypted, true);
                            $wasEncrypted = true;
                        } catch (\Exception $e) {
                            $decoded = null;
                        }
                    }

                    if (!is_array($decoded)) {
                        // cannot process form_data structure
                        $this->error++;
                        $this->errors[] = [
                            'row' => $index + 1,
                            'employee_id' => $row['employee_id'],
                            'message' => 'Unable to decode appraisal.form_data for update',
                        ];
                    } else {
                        // initialize pointer and set [0].achievement = null once per employee
                        // pastikan struktur formData KPI ada
                        if (
                            !isset($decoded['formData'][0]) ||
                            $decoded['formData'][0]['formName'] !== 'KPI'
                            ) {
                                throw new \Exception('KPI form structure not found in appraisal.form_data');
                        }
                        // dd($decoded['formData'][ 0]);
                        
                        // cari index KPI berikutnya yang achievement-nya null
                        foreach ($decoded['formData'][0] as $key => $item) {
                
                            if (!is_numeric($key)) continue;

                            $decoded['formData'][0][$index]['achievement'] = $row['achievement'];
                            break;
                        }
                        
                        // save back, re-encrypt if it was encrypted originally
                        $newData = json_encode($decoded);
                        $appraisal->form_data = $wasEncrypted ? Crypt::encryptString($newData) : $newData;
                        $appraisal->save();

                        $snapshot = ApprovalSnapshots::where('form_id', $appraisal->id)
                            ->where('created_by', $user->id)
                            ->orderBy('id', 'asc')
                            ->first();

                        if ($snapshot) {
                            $snapshot->update([
                                'employee_id' => $row['employee_id'],
                                'form_data'   => $appraisal->form_data,
                            ]);
                        } else {
                            ApprovalSnapshots::create([
                                'id'          => (string) Str::uuid(),
                                'form_id'     => $appraisal->id,
                                'created_by'  => $user->id,
                                'employee_id' => $row['employee_id'],
                                'form_data'   => $appraisal->form_data,
                            ]);
                        }
                    }
                }

                $this->success++;
            } catch (\Exception $e) {
                $this->error++;
                $this->errors[] = [
                    'row' => $index + 1,
                    'employee_id' => $row['employee_id'] ?? 'N/A',
                    'message' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Get validation rules
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|string',
            'employee_name' => 'required|string',
            'kpi' => 'required|string',
            'target' => 'required|numeric',
            'uom' => 'required|string',
            'weightage' => 'required|numeric',
            'type' => 'required|string',
            'achievement' => 'required|numeric',
            'cluster' => 'required|string',
        ];
    }

    /**
     * Get success count
     */
    public function getSuccess()
    {
        return $this->success;
    }

    /**
     * Get error count
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Get error details
     */
    public function getErrors()
    {
        return $this->errors;
    }
}
