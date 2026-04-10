<?php

namespace App\Imports;

use App\Models\ApprovalLayer;
use App\Models\ApprovalLayerBackup;
use App\Models\ApprovalRequest;
use App\Models\Employee;
use App\Models\Goal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\{
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithColumnLimit
};

class ApprovalLayerImport implements
    ToModel,
    WithHeadingRow,
    WithChunkReading,
    WithBatchInserts,
    WithColumnLimit
{
    protected $userId;
    protected $period;
    protected $invalidEmployees = [];
    protected $employeeIds = [];

    public function __construct($userId, $period)
    {
        $this->userId = $userId;
        $this->period = $period;

        // cache employee
        $this->employeeIds = Employee::pluck('employee_id')->toArray();
    }

    public function chunkSize(): int
    {
        return 100;
    }

    public function batchSize(): int
    {
        return 100;
    }

    public function endColumn(): string
    {
        return 'Z';
    }

    public function model(array $row)
    {
        $employeeId = $row['employee_id'] ?? null;
        if (!$employeeId) return null;

        DB::beginTransaction();

        try {

            $layers = [];

            for ($i = 1; $i <= 5; $i++) {
                $approverId = $row["layer_approval_id_$i"] ?? null;

                if (!$approverId) continue;

                if (!in_array($approverId, $this->employeeIds)) {
                    $this->invalidEmployees[] = $employeeId;
                    continue;
                }

                $insertData[] = [
                    'employee_id' => $employeeId,
                    'approver_id' => $approverId,
                    'layer' => $i,
                    'updated_by' => $this->userId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // ambil layer 1 untuk update ApprovalRequest
                if ($i == 1) {
                    $cached['layer1_map'][$employeeId] = $approverId;
                }
            }

            // unique check
            if (collect($layers)->unique('approver_id')->count() !== count($layers)) {
                $this->invalidEmployees[] = $employeeId;
                DB::rollBack();
                return null;
            }

            // backup
            $oldLayers = ApprovalLayer::where('employee_id', $employeeId)->get();
            foreach ($oldLayers as $layer) {
                ApprovalLayerBackup::create([
                    'employee_id' => $layer->employee_id,
                    'approver_id' => $layer->approver_id,
                    'layer' => $layer->layer,
                    'updated_by' => $layer->updated_by,
                    'created_at' => $layer->created_at,
                    'updated_at' => $layer->updated_at,
                ]);
            }

            // delete old
            ApprovalLayer::where('employee_id', $employeeId)->delete();

            // insert new
            if (!empty($layers)) {
                ApprovalLayer::insert($layers);
            }

            // update request + goal
            $newApprover = $row['layer_approval_id_1'] ?? null;

            if ($newApprover) {
                $requests = ApprovalRequest::where('employee_id', $employeeId)
                    ->where('category', 'Goals')
                    ->where('period', $this->period)
                    ->whereNull('deleted_at')
                    ->get();

                foreach ($requests as $req) {

                    if ($req->current_approval_id != $newApprover) {

                        $req->update([
                            'current_approval_id' => $newApprover,
                            'status' => 'Pending',
                        ]);

                        Goal::where('employee_id', $employeeId)
                            ->where('period', $this->period)
                            ->whereNull('deleted_at')
                            ->update([
                                'form_status' => 'Submitted',
                            ]);
                    }
                }
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();

            Log::error('Import failed', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            $this->invalidEmployees[] = $employeeId;
        }

        // also keep in-memory copies for immediate synchronous reads
        $this->employeeIds = array_merge($this->employeeIds, $cached['employee_ids']);
        $this->invalidEmployees = array_merge($this->invalidEmployees, $cached['invalid']);
        $this->layer1Map = array_merge($this->layer1Map, $cached['layer1_map']);
    }

    public function chunkSize(): int
    {
        return 200; // lower chunk size to reduce memory usage
    }

    public function batchSize(): int
    {
        return 200;
    }

    // =========================
    // GETTER
    // =========================

    public function getInvalidEmployees()
    {
        return array_unique($this->invalidEmployees);
    }

    public function getEmployeeIds()
    {
        return array_unique($this->employeeIds);
    }

    public function getLayer1Map()
    {
        return $this->layer1Map;
    }
}