<?php
namespace App\Exports;

use Carbon\Carbon;
use App\Models\PerformanceDialog;
use App\Models\ApprovalLayer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PerformanceDialogExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $period;
    protected $groupCompany;
    protected $location;
    protected $company;
    protected $permissionLocations;
    protected $permissionCompanies;
    protected $permissionGroupCompanies;

    public function __construct($period, $groupCompany, $location, $company,$permissionLocations, $permissionCompanies, $permissionGroupCompanies)
    {
        $this->period = $period;
        $this->groupCompany = $groupCompany;
        $this->location = $location;
        $this->company = $company;

        $this->permissionLocations = $permissionLocations;
        $this->permissionCompanies = $permissionCompanies;
        $this->permissionGroupCompanies = $permissionGroupCompanies;
    }

    public function collection()
    {
        $period = $this->period;
        $usedPeriod = now()->year;

        if (!empty($period)) {
            $usedPeriod = $period;
        }

        $data = [];

        $performanceDialogs = PerformanceDialog::with(['employee', 'employeeManager'])
            ->where('period', $period)
            ->where('deleted_at', null)
            ->get();

        $now = Carbon::now();

        foreach($performanceDialogs as $row) {
            $scheduleAt = $row->start_date ?? "-";
            $initiatedAt = $row->initiate_date ?? "-";
            $dueDate = $row->due_date ?? "-";
            $status = $row->status ?? "-";
            $isActionDownload = false;

            if ($scheduleAt != "-" && Carbon::parse($scheduleAt)->lt($now) && $status == "Scheduled") {
                $status = "Overdue";
            }

            if ($status != "Done" || $status == "Submitted") {
                $isActionDownload = true;
            }

            $formattedScheduleAt = $scheduleAt != "-" ? Carbon::parse($scheduleAt)->format('Y-m-d H:i:s') : '-';
            $formattedInitiatedAt = $initiatedAt != "-" ? Carbon::parse($initiatedAt)->format('Y-m-d H:i:s') : '-';
            $formattedDueDate = $dueDate != "-" ? Carbon::parse($dueDate)->format('Y-m-d H:i:s') : '-';

            $data[] = [
                "id" => $row->id,
                "employee_id" => $row->employee_id,
                "employee_name" => $row->employee?->fullname ?? "-",
                "employee_manager_id" => $row->employeeManager?->employee_id ?? "-",
                "employee_manager_name" => $row->employeeManager?->fullname ?? "-",
                "formatted_schedule_at" => $formattedScheduleAt,
                "formatted_initiated_at" => $formattedInitiatedAt,
                "summary" => $row->summary ?? "-",
                "additional_notes" => $row->additional_notes ?? "-",
                "development_plan" => $row->development_plan ?? "-",
                "formatted_due_date" => $formattedDueDate ?? "-",
                "status" => $status,
                "is_action_download" => $isActionDownload
            ];
        }

        $performanceDialogGroupByEmployeeID = $performanceDialogs->groupBy('employee_id');

        // $reportees = ApprovalLayer::with(["employee", "employeeManager"])->get();

        // foreach($reportees as $reportee) {
        //     $reporteePerformanceDialog = $performanceDialogGroupByEmployeeID[$reportee->employee_id] ?? null;

        //     if ($reporteePerformanceDialog) {
        //         continue;
        //     }

        //     $data[] = [
        //         "id" => null,
        //         "employee_id" => $reportee->employee_id,
        //         "employee_name" => $reportee->employee?->fullname ?? "-",
        //         "employee_manager_id" => $reportee->employeeManager?->employee_id ?? "-",
        //         "employee_manager_name" => $reportee->employeeManager?->fullname ?? "-",
        //         "formatted_schedule_at" => "-",
        //         "formatted_initiated_at" => "-",
        //         "summary" => "-",
        //         "additional_notes" => "-",
        //         "development_plan" => "-",
        //         "formatted_due_date" => "-",
        //         "status" => "Not Scheduled",
        //         "is_action_download" => false
        //     ];
        // }

        return collect($data);
    }

    public function headings(): array
    {
        return [
            'No',
            'Employee ID',
            'Employee Name',
            'Manager ID',
            'Manager Name',
            'Schedule Date',
            'Initiate Date',
            'Summary',
            'Additional Notes',
            'Development Plan',
            'Due Date',
            'Status',
        ];
    }

    public function map($row): array
    {
        static $no = 1;

        return [
            $no++,
            $row['employee_id'],
            $row['employee_name'],
            $row['employee_manager_id'],
            $row['employee_manager_name'],
            $row['formatted_schedule_at'],
            $row['formatted_initiated_at'],
            $row['summary'],
            $row['additional_notes'],
            $row['development_plan'],
            $row['formatted_due_date'],
            $row['status'],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['argb' => '00000000'],
                ],
            ],
        ]);

        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
