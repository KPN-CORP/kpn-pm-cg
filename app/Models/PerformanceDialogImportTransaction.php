<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceDialogImportTransaction extends Model
{
    use HasFactory;

    protected $table = 'performance_dialog_import_transactions';

    protected $fillable = [
        'success',
        'error',
        'detail_error',
        'file_uploads',
        'submit_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'submit_by');
    }
}
