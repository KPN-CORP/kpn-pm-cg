<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Employee extends Model
{
    protected $connection = 'kpncorp';
    protected $table = 'employees';

    use HasFactory;
    
    protected $fillable = [
        // Kolom-kolom lainnya,
        'access_menu','id','employee_id', 'fullname', 'gender', 'email', 'group_company',
        'designation', 'designation_name', 'job_level', 'company_name', 'contribution_level_code',
        'work_area_code', 'office_area', 'manager_l1_id', 'manager_l2_id',
        'employee_type', 'unit', 'date_of_joining', 'users_id'
    ];
    
    public function user()
    {
        return $this->belongsTo(User::class, 'users_id', 'id');
    }
    public function goal()
    {
        return $this->belongsTo(Goal::class, 'employee_id', 'employee_id');
    }
    
    public function approvalRequest()
    {
        return $this->hasMany(ApprovalRequest::class, 'employee_id', 'employee_id');
    }

    public function approvalLayer()
    {
        return $this->hasMany(ApprovalLayer::class, 'employee_id', 'id');
    }
    public function approvalManager()
    {
        return $this->hasMany(ApprovalRequest::class, 'employee_id', 'current_approval_id');
    }
    public function creatorApproverLayer()
    {
        return $this->hasMany(ApprovalLayer::class, 'creator_id', 'id');
    }
    public function schedule()
    {
        return $this->belongsTo(Schedule::class, 'bisnis_unit', 'group_company')
                ->whereRaw("FIND_IN_SET('bisnis_unit', group_company)");
    }
    public static function getUniqueGroupCompanies()
    {
        // Ambil data group_company yang unik dari tabel employee
        return self::select('group_company')
            ->distinct()
            ->pluck('group_company');
    }
    public static function getUniqueJobLevel()
    {
        return self::select('job_level')
            ->distinct()
            ->where('job_level', '!=', '1A')
            ->orderBy('job_level', 'asc')
            ->pluck('job_level');
    }

    public function appraisalLayer()
    {
        return $this->hasMany(ApprovalLayerAppraisal::class, 'employee_id', 'employee_id');
    }

    public function approvalLayerAppraisals()
    {
        return $this->hasMany(ApprovalLayerAppraisal::class, 'approver_id', 'employee_id');
    }

    public function managerL1()
    {
        return $this->belongsTo(Employee::class, 'manager_l1_id', 'employee_id');
    }
}
