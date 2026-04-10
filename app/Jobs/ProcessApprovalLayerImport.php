<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ApprovalLayerImport;
use Illuminate\Support\Facades\Cache;
use App\Models\ApprovalLayer;
use App\Models\ApprovalLayerBackup;
use App\Models\ApprovalRequest;
use App\Models\Goal;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcessApprovalLayerImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $userId;
    public $period;
    public $path;

    /**
     * Create a new job instance.
     */
    public function __construct($userId, $period, $path)
    {
        $this->userId = $userId;
        $this->period = $period;
        $this->path = $path;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        // temporarily raise PHP memory limit for large imports
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '1024M');
        }

        // ensure PhpSpreadsheet uses batch caching to avoid keeping all cells in memory
        config(['excel.cache.driver' => 'batch']);

        // mark start time so we can distinguish newly inserted rows
        $start = Carbon::now();

        $import = new ApprovalLayerImport($this->userId, $this->period);

        // run synchronous import inside the job (chunks will be processed per-chunk)
        Excel::import($import, storage_path('app/' . $this->path));

        // determine affected employee IDs by selecting rows inserted during this import
        $employeeIds = ApprovalLayer::where('created_at', '>=', $start)
            ->pluck('employee_id')
            ->unique()
            ->values()
            ->toArray();

        if (!empty($employeeIds)) {
            // 1) Backup old layers (those created before $start)
            ApprovalLayer::whereIn('employee_id', $employeeIds)
                ->where('created_at', '<', $start)
                ->chunk(500, function ($layers) {
                    $insert = [];
                    foreach ($layers as $layer) {
                        $insert[] = [
                            'employee_id' => $layer->employee_id,
                            'approver_id' => $layer->approver_id,
                            'layer' => $layer->layer,
                            'updated_by' => $layer->updated_by,
                            'created_at' => $layer->created_at,
                            'updated_at' => $layer->updated_at,
                        ];
                    }

                    if (!empty($insert)) {
                        ApprovalLayerBackup::insert($insert);
                    }
                });

            // 2) Delete old layers
            ApprovalLayer::whereIn('employee_id', $employeeIds)
                ->where('created_at', '<', $start)
                ->delete();

            // 3) Update ApprovalRequest and Goal in chunks
            ApprovalRequest::whereIn('employee_id', $employeeIds)
                ->where('category', 'Goals')
                ->where('period', $this->period)
                ->whereNull('deleted_at')
                ->chunk(500, function ($requests) {
                    foreach ($requests as $req) {
                        $employeeId = $req->employee_id;

                        // find the first-layer approver for this employee from the newly inserted rows
                        $firstLayer = ApprovalLayer::where('employee_id', $employeeId)
                            ->orderBy('layer', 'asc')
                            ->first();

                        if (!$firstLayer) continue;

                        $newApprover = $firstLayer->approver_id;
                        $oldApprover = $req->current_approval_id;

                        if ($oldApprover != $newApprover) {
                            ApprovalRequest::where('id', $req->id)->update([
                                'current_approval_id' => $newApprover,
                                'status' => 'Pending',
                            ]);

                            Goal::where('employee_id', $employeeId)
                                ->where('period', $req->period)
                                ->whereNull('deleted_at')
                                ->update([
                                    'form_status' => 'Submitted',
                                ]);
                        }
                    }
                });
        }

        // mark cache as done and add finished_at (small metadata only)
        $cacheKey = "approval_layer_import_{$this->userId}_{$this->period}";
        $cached = Cache::get($cacheKey, []);
        $cached['done'] = true;
        $cached['finished_at'] = now()->toDateTimeString();
        $cached['affected_count'] = count($employeeIds);
        Cache::put($cacheKey, $cached, now()->addHours(4));
    }
}
