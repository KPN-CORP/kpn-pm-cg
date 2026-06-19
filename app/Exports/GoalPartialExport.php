<?php

namespace App\Exports;

use App\Models\ApprovalRequest;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class GoalPartialExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping, WithCustomCsvSettings
{
    use Exportable;

    public function __construct(
        protected readonly string $period,
        protected readonly bool   $admin,
        protected readonly array  $employeeIds,       // slice of 150 IDs
        protected readonly mixed  $permissionLocations,
        protected readonly mixed  $permissionCompanies,
        protected readonly mixed  $permissionGroupCompanies,
    ) {}

    public function query(): Builder
    {
        $query = ApprovalRequest::query()
            ->where('category', 'Goals')
            ->where('period', $this->period)
            ->with([
                'employee:id,employee_id,fullname,job_level,group_company,work_area_code,contribution_level_code',
                'goal:id,form_data',
                'initiated:id,employee_id,fullname',
                'approvalLayer:id,approver_id,employee_id',
                'approvalLayer.manager:id,employee_id',
            ])
            ->whereHas('employee', fn ($q) =>
                $q->whereIn('employee_id', $this->employeeIds)
            );

        if (! $this->admin) {
            $employeeId = auth()->user()->employee_id;
            $query->whereHas('approvalLayer', fn ($q) =>
                $q->where('approver_id', $employeeId)
                  ->orWhere('employee_id', $employeeId)
            );
        }

        $this->applyEmployeeFilter($query, 'work_area_code',          $this->permissionLocations);
        $this->applyEmployeeFilter($query, 'group_company',           $this->permissionGroupCompanies);
        $this->applyEmployeeFilter($query, 'contribution_level_code', $this->permissionCompanies);

        return $query;
    }

    public function chunkSize(): int { return 200; }

    public function headings(): array
    {
        return [
            'Employee ID', 'Employee Name', 'Designation', 'Business Unit',
            'Category', 'KPI', 'Target', 'Uom', 'Weightage', 'Type',
            'Description', 'Form Status', 'Approval Status',
            'Current Approver', 'Current Approver ID',
            'Initiated By', 'Initiated By ID', 'Period',
        ];
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter'        => ',',
            'enclosure'        => '"',
            'line_ending'      => "\n",
            'use_bom'          => false,   // ✅ no BOM — causes corruption on merge
            'include_separator_line' => false,
            'excel_compatibility'    => false,
        ];
    }

    public function map($row): array
    {
        $employee  = $row->employee;
        $initiated = $row->initiated;
        $approver  = $this->resolveCurrentApprover($row);

        $base = [
            optional($employee)->employee_id,
            optional($employee)->fullname,
            optional($employee)->job_level,
            optional($employee)->group_company,
            $row->category,
            null, null, null, null, null, null, // kpi columns
            $row->form_status,
            $row->status,
            $approver['name'],
            $approver['id'],
            optional($initiated)->fullname,
            optional($initiated)->employee_id ?? optional($employee)->employee_id,
            $row->period,
        ];

        $goalItems = $this->parseGoalItems($row->goal);

        if (empty($goalItems)) {
            $base[5] = $base[6] = $base[7] = $base[8] = $base[9] = '-';
            $base[10] = '';
            return [$base];
        }

        $rows = [];
        foreach ($goalItems as $item) {
            $mapped     = $base;
            $mapped[5]  = $item['kpi']         ?? '-';
            $mapped[6]  = $item['target']       ?? '-';
            $mapped[7]  = $this->resolveUom($item);
            $mapped[8]  = $item['weightage']    ?? '-';
            $mapped[9]  = $item['type']         ?? '-';
            $mapped[10] = $item['description']  ?? '';
            $rows[]     = $mapped;
        }

        return $rows;
    }

    private function resolveUom(array $item): string
    {
        $uom       = $item['uom']        ?? '';
        $customUom = $item['custom_uom'] ?? '';
        return ($uom === 'Other' && ! empty($customUom)) ? $customUom : $uom;
    }

    private function parseGoalItems($goal): array
    {
        if (! $goal || empty($goal->form_data)) return [];

        $data = is_array($goal->form_data)
            ? $goal->form_data
            : json_decode($goal->form_data, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) return [];

        return array_values(array_filter($data, 'is_array'));
    }

    private function resolveCurrentApprover($row): array
    {
        $pending = $row->approvalLayer
            ?->first(fn ($layer) => $layer->status === 'Pending');

        return $pending ? [
            'name' => optional($pending->manager)->fullname ?? '-',
            'id'   => $pending->approver_id ?? '-',
        ] : ['name' => '-', 'id' => '-'];
    }

    private function applyEmployeeFilter(Builder $query, string $column, mixed $value): void
    {
        $values = empty($value) ? [] : (is_array($value) ? $value : array_filter(array_map('trim', explode(',', $value))));
        if (! empty($values)) {
            $query->whereHas('employee', fn ($q) => $q->whereIn($column, $values));
        }
    }
}