<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InvalidPerformanceDialogManagerImport implements FromCollection, WithHeadings
{
    protected $invalidEmployees;

    public function __construct($invalidEmployees)
    {
        $this->invalidEmployees = $invalidEmployees;
    }

    public function collection()
    {
        return collect($this->invalidEmployees);
    }

    public function headings(): array
    {
        return [
            'employee_id',
            'errors'
        ];
    }
}
