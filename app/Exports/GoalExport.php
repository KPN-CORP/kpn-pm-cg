<?php

namespace App\Exports;

use App\Models\ApprovalRequest;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class GoalExport implements FromQuery, WithChunkReading, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, ShouldQueue
{
    use Exportable;

    public function __construct(
        protected readonly string $period,
        protected readonly mixed  $groupCompany,
        protected readonly mixed  $location,
        protected readonly mixed  $company,
        protected readonly bool   $admin,
        protected readonly mixed  $permissionLocations,
        protected readonly mixed  $permissionCompanies,
        protected readonly mixed  $permissionGroupCompanies,
    ) {
        Log::debug('Goal Export Filters', [
            'period'                   => $this->period,
            'groupCompany'             => $this->groupCompany,
            'location'                 => $this->location,
            'company'                  => $this->company,
            'permissionLocations'      => $this->permissionLocations,
            'permissionCompanies'      => $this->permissionCompanies,
            'permissionGroupCompanies' => $this->permissionGroupCompanies,
            'admin'                    => $this->admin,
        ]);
    }

    // -------------------------------------------------------------------------
    // Query
    // -------------------------------------------------------------------------

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
            ]);

        if (! $this->admin) {
            $employeeId = auth()->user()->employee_id;

            $query->whereHas('approvalLayer', fn ($q) =>
                $q->where('approver_id', $employeeId)
                  ->orWhere('employee_id', $employeeId)
            );
        }

        // User filters
        $this->applyEmployeeFilter($query, 'group_company',           $this->groupCompany);
        $this->applyEmployeeFilter($query, 'work_area_code',          $this->location);
        $this->applyEmployeeFilter($query, 'contribution_level_code', $this->company);

        // Permission filters
        $this->applyEmployeeFilter($query, 'work_area_code',          $this->permissionLocations);
        $this->applyEmployeeFilter($query, 'group_company',           $this->permissionGroupCompanies);
        $this->applyEmployeeFilter($query, 'contribution_level_code', $this->permissionCompanies);

        return $query;
    }

    // -------------------------------------------------------------------------
    // Chunk size
    // -------------------------------------------------------------------------

    public function chunkSize(): int
    {
        return 500;
    }

    // -------------------------------------------------------------------------
    // Headings
    // -------------------------------------------------------------------------

    public function headings(): array
    {
        return [
            'Employee ID',
            'Employee Name',
            'Designation',
            'Business Unit',
            'Category',
            'KPI',
            'Target',
            'Uom',
            'Weightage',
            'Type',
            'Description',
            'Form Status',
            'Approval Status',
            'Current Approver',
            'Current Approver ID',
            'Initiated By',
            'Initiated By ID',
            'Period',
        ];
    }

    // -------------------------------------------------------------------------
    // Map — expands one ApprovalRequest into N rows (one per KPI item)
    // -------------------------------------------------------------------------

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
            null, // kpi
            null, // target
            null, // uom
            null, // weightage
            null, // type
            null, // description
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
            $base[5]  = '-';
            $base[6]  = '-';
            $base[7]  = '-';
            $base[8]  = '-';
            $base[9]  = '-';
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

    // -------------------------------------------------------------------------
    // Styles
    // -------------------------------------------------------------------------

    public function styles($sheet): array
    {
        // Header row
        $sheet->getStyle('A1:R1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType'   => 'solid',
                'startColor' => ['rgb' => 'FFFF00'],
            ],
        ]);

        // Percentage format on Weightage column (I)
        $sheet->getStyle('I:I')
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE);

        // ✅ Apply ONE validation to the entire column range — no loop needed
        $validation = $sheet->getCell('J2')->getDataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"Lower Better,Higher Better,Exact Value"');
        $validation->setSqref('J2:J1048576'); // entire column in one shot

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parse KPI line-items from goal.form_data JSON.
     *
     * Expected JSON shape (adjust keys to match your schema):
     * [
     *   {
     *     "kpi": "% Productivity",
     *     "target": "10000%",
     *     "uom": "Percent (%)",
     *     "weightage": 0.18,
     *     "type": "Higher Better",
     *     "description": ""
     *   },
     *   ...
     * ]
     */

    private function resolveUom(array $item): string
    {
        $uom       = $item['uom']        ?? '';
        $customUom = $item['custom_uom'] ?? '';

        return ($uom === 'Other' && ! empty($customUom))
            ? $customUom
            : $uom;
    }

    private function parseGoalItems($goal): array
    {
        if (! $goal || empty($goal->form_data)) {
            return [];
        }

        $data = is_array($goal->form_data)
            ? $goal->form_data
            : json_decode($goal->form_data, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($data)) {
            return [];
        }

        // Top-level array of items — no wrapper key needed
        return array_values(array_filter($data, 'is_array'));
    }

    /**
     * Resolve current approver from approvalLayer.
     *
     * Status enum: 'Approved' | 'Pending' | 'Sendback'
     * 'Pending' = waiting for approval (displayed as "Waiting for Approval" in UI
     * but stored as 'Pending' in DB).
     */
    private function resolveCurrentApprover($row): array
    {
        $pending = $row->approvalLayer
            ?->first(fn ($layer) => $layer->status === 'Pending');

        if (! $pending) {
            return ['name' => '-', 'id' => '-'];
        }

        return [
            'name' => optional($pending->approver)->fullname ?? '-',
            'id'   => $pending->approver_id ?? '-',
        ];
    }

    private function applyEmployeeFilter(Builder $query, string $column, mixed $value): void
    {
        $values = $this->normalizeFilter($value);

        if (! empty($values)) {
            $query->whereHas('employee', fn ($q) => $q->whereIn($column, $values));
        }
    }

    private function normalizeFilter(mixed $value): array
    {
        if (empty($value)) {
            return [];
        }

        return is_array($value)
            ? $value
            : array_filter(array_map('trim', explode(',', $value)));
    }
}