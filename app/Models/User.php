<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    protected $connection = 'kpncorp';

    /**
     * Get the identifier that will be stored in the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key-value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    
    use HasFactory, Notifiable, HasRoles;

    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'employee_id',
        'name',
        'email',
        'password',
        'token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'employee_id', 'employee_id');
    }
    public function approvalRequest()
    {
        return $this->hasMany(ApprovalRequest::class, 'employee_id', 'employee_id');
    }

    public function isApprover()
    {
        return $this->approver_layers()->exists();
    }
    
    public function approver_layers()
    {
        return $this->hasMany(ApprovalLayer::class, 'approver_id', 'employee_id');
    }

    public function isCalibrator()
    {
        return $this->appraisals_calibrator()->exists();
    }

    public function appraisals_calibrator()
    {
        return $this->hasMany(ApprovalLayerAppraisal::class, 'approver_id', 'employee_id')->where('layer_type', 'calibrator');
    }

    public function kpiUnits()
    {
        return $this->check_kpi_units()->exists();
    }

    public function check_kpi_units()
    {
        return $this->belongsTo(KpiUnits::class, 'employee_id', 'employee_id')->orderBy('periode', 'desc');
    }

    public function cekBUCement()
    {
        return $this->belongsTo(EmployeeAppraisal::class, 'employee_id', 'employee_id')->whereIn('group_company', ['Cement']);
    }

    public function isCement()
    {
        return $this->cekBUCement()->exists();
    }
}
