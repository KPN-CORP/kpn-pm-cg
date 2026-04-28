<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MasterDesignationWeightage extends Model {
    use HasFactory;
    use SoftDeletes;

    protected $table = 'master_designation_weightages';

    protected $fillable = [
        "job_code",
        "weightage_type",
        "company_kpi",
        "dept_kpi",
        "dev_kpi",
        "cluster",
        "dept_head_flag",
        "director_flag",
        "created_by",
        "updated_by",
        "created_at",
        "updated_at",
        "deleted_at"
    ];
}
