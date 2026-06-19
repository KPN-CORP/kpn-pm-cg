<?php

namespace App\Jobs;

use App\Exports\GoalPartialExport;
use App\Models\Employee;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

class GoalExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        protected string $period,
        protected mixed  $groupCompany,
        protected mixed  $location,
        protected mixed  $company,
        protected bool   $admin,
        protected mixed  $permissionLocations,
        protected mixed  $permissionCompanies,
        protected mixed  $permissionGroupCompanies,
        protected int    $requestedBy,           // auth user ID to notify when done
        protected string $exportKey,             // unique key e.g. "goal_export_{userId}_{timestamp}"
    ) {}

    public function handle(): void
    {
        $tmpFolder   = 'public/exports/goal/tmp/' . $this->exportKey;
        $chunkSize   = 150;
        $partIndex   = 0;
        $partialJobs = [];
        $fileName = "{$this->exportKey}.xlsx";
        $filePath = "public/exports/goal/{$fileName}";

        // ✅ Stream IDs from DB in chunks — never loads all into memory at once
        $this->buildEmployeeQuery()
            ->select('employee_id')
            ->orderBy('employee_id')
            ->chunk($chunkSize, function ($employees) use (&$partialJobs, &$partIndex, $tmpFolder) {

                $partialJobs[] = new GoalPartialWriteJob(
                    period:                   $this->period,
                    admin:                    $this->admin,
                    employeeIds:              $employees->pluck('employee_id')->all(),
                    permissionLocations:      $this->permissionLocations,
                    permissionCompanies:      $this->permissionCompanies,
                    permissionGroupCompanies: $this->permissionGroupCompanies,
                    tmpPath:                  "{$tmpFolder}/part_{$partIndex}.csv",
                    tmpFolder:                $tmpFolder,
                    totalParts:               0, // patched below
                    exportKey:                $this->exportKey,
                    requestedBy:             $this->requestedBy,
                    partIndex:                $partIndex,
                );

                $partIndex++;
            });

        foreach (Storage::files('public/exports/goal') as $oldFile) {

            $baseName = basename($oldFile);

            if (
                preg_match("/^goal_{$this->requestedBy}_\d+\.xlsx$/", $baseName) &&
                $baseName !== $fileName
            ) {
                Storage::delete($oldFile);
            }
        }
        
        // if (empty($partialJobs)) {
        //     $this->notifyDone(null, 'No data found for the selected filters.');
        //     return;
        // }

        // Patch totalParts now that we know the real count
        $totalParts = count($partialJobs);
        foreach ($partialJobs as $job) {
            $job->setTotalParts($totalParts);
        }

        Bus::batch($partialJobs)
            ->name("Goal Export [{$this->exportKey}]")
            ->allowFailures(false)
            ->dispatch();
    }

    // ✅ Extracted — reusable query builder with all filters, no ->get()
    private function buildEmployeeQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = \App\Models\Employee::query();

        if (! empty($this->groupCompany)) {
            $query->whereIn('group_company', $this->normalize($this->groupCompany));
        }
        if (! empty($this->location)) {
            $query->whereIn('work_area_code', $this->normalize($this->location));
        }
        if (! empty($this->company)) {
            $query->whereIn('contribution_level_code', $this->normalize($this->company));
        }
        if (! empty($this->permissionLocations)) {
            $query->whereIn('work_area_code', $this->normalize($this->permissionLocations));
        }
        if (! empty($this->permissionGroupCompanies)) {
            $query->whereIn('group_company', $this->normalize($this->permissionGroupCompanies));
        }
        if (! empty($this->permissionCompanies)) {
            $query->whereIn('contribution_level_code', $this->normalize($this->permissionCompanies));
        }

        return $query;
    }

    private function resolveEmployeeIds(): \Illuminate\Support\Collection
    {
        $query = \App\Models\Employee::query()->select('employee_id');

        if (! empty($this->groupCompany)) {
            $query->whereIn('group_company', $this->normalize($this->groupCompany));
        }
        if (! empty($this->location)) {
            $query->whereIn('work_area_code', $this->normalize($this->location));
        }
        if (! empty($this->company)) {
            $query->whereIn('contribution_level_code', $this->normalize($this->company));
        }
        if (! empty($this->permissionLocations)) {
            $query->whereIn('work_area_code', $this->normalize($this->permissionLocations));
        }
        if (! empty($this->permissionGroupCompanies)) {
            $query->whereIn('group_company', $this->normalize($this->permissionGroupCompanies));
        }
        if (! empty($this->permissionCompanies)) {
            $query->whereIn('contribution_level_code', $this->normalize($this->permissionCompanies));
        }

        return $query->pluck('employee_id');
    }

    private function normalize(mixed $value): array
    {
        if (empty($value))    return [];
        if (is_array($value)) return $value;
        return array_filter(array_map('trim', explode(',', $value)));
    }

    // private function notifyDone(?string $path, string $message = ''): void
    // {
    //     Swap for your notification system — broadcast, mail, DB notification, etc.
    //     \App\Models\User::find($this->requestedBy)?->notify(
    //         new \App\Notifications\ExportReadyNotification($path, $message)
    //     );
    // }
}