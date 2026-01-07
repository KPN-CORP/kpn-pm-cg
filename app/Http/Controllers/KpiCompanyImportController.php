<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\KpiCompanyImport;
use App\Models\KpiCompanyImportTransaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Response;

class KpiCompanyImportController extends Controller
{
    /**
     * Show import form for KPI Company Achievement
     */
    public function showImportForm()
    {
        $userId = Auth::id();
        $parentLink = 'Settings';
        $link = 'KPI Company Import';
        
        $imports = KpiCompanyImportTransaction::orderBy('created_at', 'desc')->get();
                            
        return view('pages.imports.import-kpi-company', [
            'link' => $link,
            'parentLink' => $parentLink,
            'userId' => $userId,
            'imports' => $imports,
        ]);
    }

    /**
     * Import KPI Company Achievement data
     */
    public function import(Request $request)
    {
        // Validasi file
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
            'period' => 'required|integer|digits:4',
        ]);
        
        // Pastikan file terupload
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('uploads');
            Log::info("KPI Company Achievement file uploaded: " . $filePath);
        } else {
            Log::error("KPI Company Achievement file upload failed.");
            return back()->with('error', "File upload failed.");
        }

        try {
            $period = $request->input('period');
            $import = new KpiCompanyImport($period);
            
            Excel::import($import, $filePath);

            // Get import results
            $success = $import->getSuccess();
            $error = $import->getError();
            $errors = $import->getErrors();

            // Simpan transaksi
            $transaction = KpiCompanyImportTransaction::create([
                'file_uploads' => $filePath,
                'success' => $success,
                'error' => $error,
                'detail_error' => count($errors) > 0 ? $errors : null,
                'import_type' => 'achievement',
            ]);

            Log::info("KPI Company Achievement imported successfully. Success: {$success}, Error: {$error}");
            
            return redirect()->back()->with('success', "Import completed! Success: {$success}, Error: {$error}");
        } catch (\Exception $e) {
            Log::error("KPI Company Achievement import failed: " . $e->getMessage());
            return back()->with('error', "Import failed: " . $e->getMessage());
        }
    }

    /**
     * Download file
     */
    public function downloadExcel($file)
    {
        $filePath = storage_path($file);

        if (file_exists($filePath)) {
            return Response::download($filePath);
        }

        abort(404, 'File not found');
    }
}
