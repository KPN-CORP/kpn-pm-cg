<?php

namespace App\Exports;

use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class GoalExport implements FromView, WithStyles
{
    use Exportable;

    protected $groupCompany;
    protected $location;
    protected $company;
    protected $period;
    protected $admin;
    protected $permissionGroupCompanies;
    protected $permissionCompanies;
    protected $permissionLocations;
    protected $goals;

    public function __construct($period, $groupCompany, $location, $company, $admin, $permissionLocations, $permissionCompanies, $permissionGroupCompanies)
    {
        $this->groupCompany = $groupCompany;
        $this->location = $location;
        $this->company = $company;
        $this->admin = $admin;
        $this->period = $period;

        $this->permissionLocations = $permissionLocations;
        $this->permissionCompanies = $permissionCompanies;
        $this->permissionGroupCompanies = $permissionGroupCompanies;

        Log::debug('Goal Export Filters', [
            'period' => $this->period,
            'groupCompany' => $this->groupCompany,
            'location' => $this->location,
            'company' => $this->company,
            'permissionLocations' => $this->permissionLocations,
            'permissionCompanies' => $this->permissionCompanies,
            'permissionGroupCompanies' => $this->permissionGroupCompanies,
            'admin' => $this->admin,
        ]);
  
    }

    public function view(): View
    {
        $query = ApprovalRequest::query();

        $query->where('category', 'Goals')
            ->where('period', $this->period);

        if (!$this->admin) {
            $query->whereHas('approvalLayer', function ($query) {
                $query->where('approver_id', auth()->user()->employee_id)
                    ->orWhere('employee_id', auth()->user()->employee_id);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | User Filters (Multi Select)
        |--------------------------------------------------------------------------
        */

        if (!empty($this->groupCompany)) {

            $groupCompanies = is_array($this->groupCompany)
                ? $this->groupCompany
                : [$this->groupCompany];

            $query->whereHas('employee', function ($query) use ($groupCompanies) {
                $query->whereIn('group_company', $groupCompanies);
            });
        }

        if (!empty($this->location)) {

            $locations = is_array($this->location)
                ? $this->location
                : [$this->location];

            $query->whereHas('employee', function ($query) use ($locations) {
                $query->whereIn('work_area_code', $locations);
            });
        }

        if (!empty($this->company)) {

            $companies = is_array($this->company)
                ? $this->company
                : [$this->company];

            Log::debug('Applying Company Filter', [
                'values' => $companies,
            ]);

            $query->whereHas('employee', function ($query) use ($companies) {
                $query->whereIn('contribution_level_code', $companies);
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Permission Filters
        |--------------------------------------------------------------------------
        */

        $criteria = [
            'work_area_code' => $this->permissionLocations,
            'group_company' => $this->permissionGroupCompanies,
            'contribution_level_code' => $this->permissionCompanies,
        ];

        foreach ($criteria as $column => $values) {

            if (!empty($values)) {

                $query->whereHas('employee', function ($subQuery) use ($column, $values) {

                    $subQuery->whereIn(
                        $column,
                        is_array($values) ? $values : [$values]
                    );

                });
            }
        }

        $this->goals = $query
            ->with([
                'employee',
                'manager',
                'goal',
                'initiated',
                'approvalLayer'
            ])
            ->get();

        return view('exports.goal', [
            'goals' => $this->goals
        ]);
    }

    public function styles($sheet)
    {
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);

        // Count total rows from $data and multiply by 10
        $totalRows = isset($this->goals) ? count($this->goals) * 10 : 10; // Default to 10 if no data

        // Apply dropdown selection (Lower Better, Higher Better, Exact Value) to column D
        $validation = $sheet->getCell('H2')->getDataValidation(); // Start from row 2
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"Lower Better,Higher Better,Exact Value"'); // Dropdown options

        // Apply to all rows in column G (Adjust range as needed)
        for ($row = 2; $row <= $totalRows; $row++) { // Adjust 100 based on data size
            $sheet->getCell("H$row")->setDataValidation(clone $validation);
        }

        $sheet->getStyle('G:G')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE);

        return [
            1 => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFFF00']]
            ],
        ];
    }
}
