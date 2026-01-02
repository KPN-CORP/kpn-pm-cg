<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;

class EmployeeRatingExport implements FromCollection, WithHeadings, WithEvents, ShouldAutoSize
{
    protected $ratingDatas;
    protected $level;
    protected $ratings;

    public function __construct($ratingDatas, $level, $ratings)
    {
        $this->ratingDatas = $ratingDatas;
        $this->level = $level;
        $this->ratings = $ratings;
        // Define dropdown options that will be applied to "Your Rating"
    }

    public function collection()
    {
        // Format data to be exported
        return collect($this->ratingDatas[$this->level])->map(function ($item) {
            return [
                'Employee_Name'    => $item->employee->fullname,
                'Employee_ID'      => $item->employee->employee_id,
                'Designation'      => $item->employee->designation_name,
                'Unit'             => $item->employee->unit,
                'Approver_Rating_Name' => $item->approver->fullname,
                'Approver_Rating_ID'   => $item->approver->employee_id,
                'Rating_Status'    => $item->rating_value ? 'Approved' : 'Pending',
                'Current_Approver'    => $item->approval_requests ? ($item->rating_allowed['status'] ? ($item->current_calibrator ? $item->current_calibrator : ($item->rating_incomplete ? 'On Manager Review' : '-')) : '360 Incompleted') : 'No Appraisal',
                'Score_to_Rating' => $item->suggested_rating ?? '-',
                'Previous_Rating'  => $item->previous_rating ?? '-',
                'Your_Rating'      => $this->ratings[$item->rating_value] ?? '-',  // This column needs dropdown
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee_Name',
            'Employee_ID',
            'Designation',
            'Unit',
            'Approver_Rating_Name',
            'Approver_Rating_ID',
            'Rating_Status',
            'Current_Approver',
            'Score_to_Rating',
            'Previous_Rating',
            'Your_Rating'
        ];
    }

    /**
     * Register events to apply customizations
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                // Get the highest row number of data in the sheet
                $highestRow = $event->sheet->getHighestDataRow();

                // Apply dropdown for each "Your Rating" cell from row 2 to the last row
                $this->applyDropdownToRatingColumn($event->sheet, 2, $highestRow);
            },
        ];
    }

    /**
     * Apply dropdown options for "Your Rating" column to every row
     *
     * @param $sheet
     * @param int $startRow
     * @param int $endRow
     */
    private function applyDropdownToRatingColumn($sheet, int $startRow, int $endRow)
    {
        // Define the column for "Your Rating" (Column K in this case)
        $column = 'K';

        // Convert dropdown options to a comma-separated string for data validation
        $dropdownValues = implode(',', $this->ratings);

        // Loop through each row and apply the dropdown list
        for ($row = $startRow; $row <= $endRow; $row++) {
            $cell = $column . $row;
            $validation = $sheet->getDelegate()->getCell($cell)->getDataValidation();
            $validation->setType(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::TYPE_LIST);
            $validation->setErrorStyle(\PhpOffice\PhpSpreadsheet\Cell\DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowInputMessage(true);
            $validation->setShowErrorMessage(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1('"' . $dropdownValues . '"');  // Apply dropdown options to the cell
        }
    }
}
