<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Approval extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = ['request_id', 'approver_id', 'status', 'messages','created_by','created_at'];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
    public function approverName()
    {
        return $this->belongsTo(Employee::class, 'approver_id', 'employee_id');
    }
}
