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
        "summary",
        "development_plan",
        "additional_notes",
        "period",
        "due_date",
        "type_ids",
        "others_type_name",
        "status",
        "created_by",
        "created_at",
        "updated_by",
        "updated_at",
        "deleted_by",
        "deleted_at",
    ];

    protected $casts = [
        'type_ids' => 'array',
    ];

    protected $appends = [
        'type_datas',
    ];

    protected static $performanceReviewTypes = null;

    public function getTypeDatasAttribute()
    {
        if (empty($this->type_ids)) {
            return collect();
        }

        if (self::$performanceReviewTypes === null) {
            self::$performanceReviewTypes = PerformanceReviewType::select('id', 'name')
                ->get()
                ->keyBy('id');
        }

        return collect($this->type_ids)
            ->filter(fn ($id) => isset(self::$performanceReviewTypes[$id]))
            ->map(fn ($id) => [
                'id' => self::$performanceReviewTypes[$id]->id,
                'name' => self::$performanceReviewTypes[$id]->name,
            ])
            ->values();
    }
}
