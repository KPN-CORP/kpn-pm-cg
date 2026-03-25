<?php

namespace App\Imports;

use App\Models\ApprovalLayer;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ApprovalLayerImport implements ToModel, WithHeadingRow
{
    protected $userId;
    protected $invalidEmployees = [];

    public function __construct($userId)
    {
        $this->userId = $userId;
    }
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */

    public function model(array $row)
    {
        $employeeId = $row['employee_id'];

        if (!$employeeId) return null;

        $layers = [];

        // max 5 layer
        for ($i = 1; $i <= 5; $i++) {

            $approverId = $row["layer_approval_id_$i"] ?? null;

            if (!Employee::where('employee_id', $approverId)->exists()) {
                $this->invalidEmployees[] = $employeeId;
                continue;
            }

            $unique = collect($layers)->unique('approver_id');

            if ($unique->count() !== count($layers)) {
                $this->invalidEmployees[] = $employeeId;
                return null;
            }

            if ($approverId) {

                $layers[] = [
                    'employee_id' => $employeeId,
                    'approver_id' => $approverId,
                    'layer' => $i,
                    'updated_by' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // VALIDASI max layer
        if (count($layers) > 5) {
            $this->invalidEmployees[] = $employeeId;
            return null;
        }

        // INSERT MULTIPLE
        if (!empty($layers)) {
            ApprovalLayer::insert($layers);

        }

        return null;
    }

    public function getInvalidEmployees()
    {
        return $this->invalidEmployees;
    }
}
