<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalLayer extends Model
{
    use HasFactory;
    protected $fillable = ['employee_id', 'approver_id', 'layer', 'updated_by'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function subordinates()
    {
        return $this->hasMany(ApprovalRequest::class, 'employee_id', 'employee_id');
    }

    public function manager()
    {
        return $this->belongsTo(ApprovalLayer::class, 'approver_id');
    }
    public function previousApprovers()
    {
        return $this->hasMany(Employee::class, 'employee_id', 'approver_id');
    }
    public function view_employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
}
