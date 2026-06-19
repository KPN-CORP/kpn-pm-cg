<?php

namespace App\Jobs;

use App\Exports\GoalPartialExport;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class GoalPartialWriteJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries   = 2;

    public function __construct(
        protected string $period,
        protected bool   $admin,
        protected array  $employeeIds,
        protected mixed  $permissionLocations,
        protected mixed  $permissionCompanies,
        protected mixed  $permissionGroupCompanies,
        protected string $tmpPath,
        // merge coordination — no closures, just plain values
        protected string $tmpFolder,
        protected int    $totalParts,
        protected string $exportKey,
        protected int    $requestedBy,
        protected int    $partIndex,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) return;

        Excel::store(
            new GoalPartialExport(
                period:                   $this->period,
                admin:                    $this->admin,
                employeeIds:              $this->employeeIds,
                permissionLocations:      $this->permissionLocations,
                permissionCompanies:      $this->permissionCompanies,
                permissionGroupCompanies: $this->permissionGroupCompanies,
            ),
            $this->tmpPath,          // must end in .csv
            'local',                 // disk
            \Maatwebsite\Excel\Excel::CSV  // writerType
        );

        $written = count(Storage::disk('local')->files($this->tmpFolder));

        if ($written >= $this->totalParts) {
            GoalMergeJob::dispatch(
                tmpFolder:   $this->tmpFolder,
                totalParts:  $this->totalParts,
                exportKey:   $this->exportKey,
                requestedBy: $this->requestedBy,
            );
        }
    }

    // Add this method to GoalPartialWriteJob.php
    public function setTotalParts(int $totalParts): void
    {
        $this->totalParts = $totalParts;
    }
}