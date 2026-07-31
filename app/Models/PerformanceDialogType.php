<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceDialogType extends Model
{
    use HasFactory;

    protected $table = 'performance_dialog_types';

    protected $fillable = [
        "name",
        "is_active",
        "created_by",
        "created_at",
        "updated_by",
        "updated_at",
        "deleted_by",
        "deleted_at",
    ];
}
