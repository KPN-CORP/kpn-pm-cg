<?php

namespace App\Imports;

use App\Models\ApprovalLayer;
use App\Models\Employee;
// removed ShouldQueue to allow synchronous imports when needed
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Illuminate\Support\Collection;

class ApprovalLayerImport implements ToCollection, WithHeadingRow, WithChunkReading, WithBatchInserts
{
    protected $userId;
    protected $period;

    protected $invalidEmployees = [];
    protected $employeeIds = [];
    protected $layer1Map = [];

    protected $employeeCache = [];
    protected $cacheKey;

    public function __construct($userId, $period)
    {
        $this->userId = $userId;
        $this->period = $period;

        // preload employee (anti N+1)
        $this->employeeCache = Employee::pluck('employee_id')->toArray();

        // set cache key for storing incremental results (useful for queued imports)
        $this->cacheKey = "approval_layer_import_{$this->userId}_{$this->period}";

        // prevent PHP timeout for large imports
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
    }

    public function collection(Collection $rows)
    {
        $insertData = [];

        // load any existing cached results so we can merge per-chunk (keeps memory bounded)
        $cached = Cache::get($this->cacheKey, [
            'employee_ids' => [],
            'layer1_map' => [],
            'invalid' => [],
        ]);

        foreach ($rows as $row) {
            $employeeId = $row['employee_id'] ?? null;

            if (!$employeeId) continue;

            $cached['employee_ids'][] = $employeeId;

            $layers = [];
            $approverSeen = [];

            for ($i = 1; $i <= 5; $i++) {
                $approverId = $row["layer_approval_id_$i"] ?? null;
                if (!$approverId) continue;

                // validasi employee
                if (!in_array($approverId, $this->employeeCache)) {
                    $cached['invalid'][] = $employeeId;
                    continue;
                }

                // validasi duplicate layer
                if (in_array($approverId, $approverSeen, true)) {
                    $cached['invalid'][] = $employeeId;
                    continue;
                }

                $approverSeen[] = $approverId;

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

            // insert per-row batches handled after loop
        }

        // batch insert for this chunk
        if (!empty($insertData)) {
            try {
                ApprovalLayer::insert($insertData);
            } catch (\Throwable $e) {
                Log::error('ApprovalLayerImport chunk insert failed', ['error' => $e->getMessage()]);
            }
        }

        // dedupe cached values and persist for later retrieval
        $cached['employee_ids'] = array_values(array_unique($cached['employee_ids']));
        $cached['invalid'] = array_values(array_unique($cached['invalid']));

        Cache::put($this->cacheKey, $cached, now()->addMinutes(30));

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
        return $this->invalidEmployees;
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