<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Proposed360 extends Model
{
    use HasFactory;

    protected $table = 'proposed_360_transactions';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id','employee_id','proposer_employee_id','scope',
        'peers','subordinates','managers',
        'status','approval_flow_id','current_step',
        'appraisal_year','notes','created_by','updated_by'
    ];

    protected $casts = [
        'peers'=>'array','subordinates'=>'array','managers'=>'array',
        'current_step'=>'integer','appraisal_year'=>'integer'
    ];

    protected static function booted()
    {
        static::creating(function ($m) {
            if (!$m->id) $m->id = (string) Str::uuid();
        });
    }

    public function approvalFlow(){ return $this->belongsTo(ApprovalFlow::class,'approval_flow_id'); }
    public function employee(){ return $this->belongsTo(Employee::class,'employee_id','employee_id'); }
    public function proposer(){ return $this->belongsTo(Employee::class,'proposer_employee_id','employee_id'); }
}