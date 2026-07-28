<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceDialog extends Model
{
    use HasFactory;

    protected $table = 'performance_dialogs';

    protected $fillable = [
        "manager_employee_id",
        "employee_id",
        "summary",
        "development_plan",
        "additional_notes",
        "period",
        "initiate_date",
        "start_date",
        "end_date",
        "due_date",
        "type_ids",
        "others_type_name",
        "status",
        "created_by",
        "created_at",
        "updated_by",
        "updated_at",
        "deleted_by",
        "deleted_at",
    ];

    protected $casts = [
        'type_ids' => 'array',
    ];

    protected $appends = [
        'type_datas',
    ];

    protected static $performanceDialogTypes = null;

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id','employee_id');
    }

    public function employeeManager()
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id','employee_id');
    }

    public function employeeCreatedBy()
    {
        return $this->belongsTo(Employee::class, 'created_by','id');
    }

    public function employeeUpdatedBy()
    {
        return $this->belongsTo(Employee::class, 'updated_by','id');
    }

    public function getTypeDatasAttribute()
    {
        if (empty($this->type_ids)) {
            return collect();
        }

        if (self::$performanceDialogTypes === null) {
            self::$performanceDialogTypes = PerformanceDialogType::select('id', 'name')
                ->get()
                ->keyBy('id');
        }

        return collect($this->type_ids)
            ->filter(fn ($id) => isset(self::$performanceDialogTypes[$id]))
            ->map(fn ($id) => [
                'id' => self::$performanceDialogTypes[$id]->id,
                'name' => self::$performanceDialogTypes[$id]->name,
            ])
            ->values();
    }
}
