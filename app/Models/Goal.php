<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    public function approvalRequest()
    {
        return $this->belongsTo(ApprovalRequest::class, 'id', 'form_id');
    }
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
    public function employeeAppraisal()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'employee_id', 'employee_id');
    }
    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class, 'id', 'goals_id');
    }

}
