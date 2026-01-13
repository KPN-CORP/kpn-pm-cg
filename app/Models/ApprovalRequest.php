<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'form_id',
        'category',
        'current_approval_id',
        'approval_flow_id',      // Kolom baru
        'total_steps',           // Kolom baru
        'current_step',          // Kolom baru
        'employee_id',
        'status',
        'messages',
        'sendback_messages',
        'sendback_to',
        'period',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'period' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime', // Untuk soft delete
        'total_steps' => 'integer', // Cast kolom baru
        'current_step' => 'integer', // Cast kolom baru
    ];

    public function approvalFlow()
    {
        return $this->belongsTo(ApprovalFlow::class, 'approval_flow_id');
    }

    public function logs()
    {
        return $this->hasMany(ApprovalLog::class, 'approval_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'employee_id', 'employee_id');
    }
    public function goal()
    {
        return $this->belongsTo(Goal::class, 'form_id', 'id');
    }
    public function approvalLayer()
    {
        return $this->hasMany(ApprovalLayer::class, 'employee_id', 'employee_id');
    }
    public function approvalLayerAppraisal()
    {
        return $this->hasMany(ApprovalLayerAppraisal::class, 'employee_id', 'employee_id');
    }
    public function employee()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'employee_id', 'employee_id');
    }

    public function manager()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'current_approval_id', 'employee_id')->withTrashed();
    }
    public function approval()
    {
        return $this->hasMany(Approval::class, 'request_id');
    }
    public function initiated()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by', 'id')->select(['id','employee_id', 'name']);
    }
    public function adjustedBy()
    {
        return $this->belongsTo(ModelHasRole::class, 'updated_by', 'model_id')->select(['model_id']);
    }
    public function appraisal()
    {
        return $this->belongsTo(Appraisal::class, 'form_id', 'id');
    }
    public function contributor()
    {
        return $this->hasMany(AppraisalContributor::class, 'employee_id', 'employee_id');
    }

    public function calibrator()
    {
        return $this->belongsTo(ApprovalLayerAppraisal::class, 'current_approval_id', 'approver_id');
    }
    public function calibration()
    {
        return $this->hasMany(Calibration::class, 'appraisal_id', 'form_id');
    }

}
