<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceReview extends Model
{
    use HasFactory;

    protected $table = 'performance_reviews';

    protected $fillable = [
        "manager_employee_id",
        "employee_id",
        "period",
        "status",
        "created_by",
        "created_at",
        "updated_by",
        "updated_at",
        "deleted_by",
        "deleted_at",
    ];
}
