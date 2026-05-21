<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Appraisal extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['form_data', 'form_status', 'updated_by', 'file'];

    public function approvalRequest()
    {
        return $this->belongsTo(ApprovalRequest::class, 'id', 'form_id');
    }
    public function goal()
    {
        return $this->belongsTo(Goal::class, 'goals_id', 'id');
    }
    public function employee()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'employee_id', 'employee_id');
    }
    public function formGroupAppraisal()
    {
        return $this->belongsTo(FormGroupAppraisal::class, 'form_group_id', 'id');
    }
    public function approvalSnapshots()
    {
        return $this->belongsTo(ApprovalSnapshots::class, 'id', 'form_id');
    }

}
