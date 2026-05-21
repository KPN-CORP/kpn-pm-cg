<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalLayerAppraisal extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'approver_id',
        'layer_type',
        'layer',
        'created_by',
        'created_at',
        'updated_by',
        'updated_at',
    ];

    public function employee()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'employee_id', 'employee_id');
    }
    public function approver()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'approver_id', 'employee_id')->withTrashed();
    }
    public function approvalRequest()
    {
        return $this->hasMany(ApprovalRequest::class, 'employee_id', 'employee_id')->where('category', 'appraisal');
    }
    public function approvalRequestApprover()
    {
        return $this->hasMany(ApprovalRequest::class, 'approver_id', 'current_approval_id')->where('category', 'appraisal');
    }
    public function contributors()
    {
        return $this->hasMany(AppraisalContributor::class, 'employee_id', 'employee_id');
    }
    public function previousApprovers()
    {
        return $this->hasMany(EmployeeAppraisal::class, 'employee_id', 'approver_id');
    }
    public function view_employee()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'employee_id', 'employee_id');
    }
    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class, 'employee_id', 'employee_id');
    }
    public function createBy()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'created_by', 'id')->select('id', 'employee_id', 'fullname');
    }
    public function updateBy()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'updated_by', 'id')->select('id', 'employee_id', 'fullname');
    }
    public function goal()
    {
        return $this->hasMany(Goal::class, 'employee_id', 'employee_id');
    }
}
