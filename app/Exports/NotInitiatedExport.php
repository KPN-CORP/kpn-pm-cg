<?php

namespace App\Exports;

use App\Models\ApprovalLayer;
use App\Models\Employee;
use App\Services\AppService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class NotInitiatedExport implements FromView, WithStyles
{
    use Exportable;

    protected $employeeId;
    protected $period;
    protected $category;
    protected $data;

    public function __construct($employeeId, $period)
    {
        $this->category = 'Goals';
        $this->employeeId = $employeeId;
        $this->period = $period;
    }

    public function view(): View
    {
        $user = $this->employeeId;

        if (auth()->user()->isApprover()) {

            // 1. Ambil employee valid dari DB kpncorp
            $validEmployees = Employee::where('access_menu->doj', 1)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('employee_id');

            // 2. Query ApprovalLayer TANPA whereHas
            $this->data = ApprovalLayer::where('approver_id', $user)
                ->whereIn('employee_id', $validEmployees->keys())
                ->whereDoesntHave('subordinates', function ($query) use ($user) {
                    $query->where('period', $this->period)
                        ->where('category', $this->category)
                        ->where('approver_id', $user);
                })
                ->get();

            // 3. Attach employee + formatting
            $this->data->map(function ($item) use ($validEmployees) {

                $employee = $validEmployees[$item->employee_id] ?? null;
                $item->employee = $employee;

                if ($employee && $employee->date_of_joining) {
                    $doj = Carbon::parse($employee->date_of_joining);
                    $item->formatted_doj = $doj->format('d M Y');
                } else {
                    $item->formatted_doj = null;
                }

                return $item;
            });

        } else {
            $this->data = collect();
        }

        return view('exports.notInitiated', ['data' => $this->data]);
    }

    // public function view(): View
    // {
    //     $user = $this->employeeId;

    //     if(Auth()->user()->isApprover()){

    //         $this->data = ApprovalLayer::with('employee')
    //         ->where('approver_id', $user)
    //         ->whereHas('employee', fn($q) => $q->where('access_menu->doj', 1))
    //         ->whereHas('employee', fn($q) => $q->whereNull('deleted_at'))
    //         ->whereDoesntHave('subordinates', function ($query) use ($user) {
    //             $query->where('period', $this->period)
    //                 ->where('category', $this->category)
    //                 ->where('approver_id', $user);
    //         }) // Ensures subordinates with these criteria do NOT exist
    //         ->get();

    //         $this->data->map(function($item) {
    //             // Format created_at
    //             $doj = Carbon::parse($item->employee->date_of_joining);

    //                 $item->formatted_doj = $doj->format('d M Y');

    //             return $item;
    //         });

    //     } else {
    //         $this->data = collect(); // Ensure it's always set
    //     }

    //     return view('exports.notInitiated', ['data' => $this->data]);

    // }

    public function styles($sheet)
    {
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);

        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Count total rows from $data and multiply by 10
        $totalRows = isset($this->data) ? count($this->data) * 10 : 10; // Default to 10 if no data

        // Apply dropdown selection (Lower Better, Higher Better, Exact Value) to column D
        $validation = $sheet->getCell('G2')->getDataValidation(); // Start from row 2
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
        $validation->setAllowBlank(false);
        $validation->setShowDropDown(true);
        $validation->setFormula1('"Lower Better,Higher Better,Exact Value"'); // Dropdown options

        // Apply to all rows in column G (Adjust range as needed)
        for ($row = 2; $row <= $totalRows; $row++) { // Adjust 100 based on data size
            $sheet->getCell("G$row")->setDataValidation(clone $validation);
        }

            // Apply percentage format to column (e.g., column C)
        $sheet->getStyle('F:F')->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_PERCENTAGE);

            return [
                1 => [
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFFF00']]
                ],
            ];
        }
}
