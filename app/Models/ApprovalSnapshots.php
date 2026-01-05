<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalSnapshots extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_id',
        'form_data',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }
    public function approvalRequest()
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id', 'id');
    }
}
