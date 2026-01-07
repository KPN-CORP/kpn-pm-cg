<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiCompanyImportTransaction extends Model
{
    use HasFactory;

    protected $table = 'kpi_company_import_transactions';
    protected $fillable = [
        'file_uploads',
        'success',
        'error',
        'detail_error',
        'import_type',
    ];

    protected $casts = [
        'detail_error' => 'json',
    ];
}
