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
            $filePath = $request->file('file')->store('public/uploads');
            $filePathDb = $request->file('file')->store('uploads');
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
            $error   = $import->getError();
            $errors  = $import->getErrors();

            // Normalize error detail
            $detailError = $this->normalizeImportErrors($errors);

            // Simpan transaksi
            $transaction = KpiCompanyImportTransaction::create([
                'file_uploads' => $filePathDb,
                'success'      => $success,
                'error'        => $error,
                'detail_error' => $detailError,
                'import_type'  => 'achievement',
            ]);

            Log::info("KPI Company Achievement imported successfully", [
                'success' => $success,
                'error'   => $error,
            ]);

            return redirect()->back()->with(
                'success',
                "Import completed! Success: {$success}, Error: {$error}"
            );

        } catch (\Throwable $e) {
            Log::error("KPI Company Achievement import failed", [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return back()->with('error', "Import failed: {$e->getMessage()}");
        }

    }

    private function normalizeImportErrors($errors)
    {
        if (empty($errors) || !is_array($errors)) {
            return null;
        }

        $normalized = [];

        foreach ($errors as $error) {
            $message = $error['message'] ?? 'Unknown error';

            $errorCode = 'UNKNOWN_ERROR';
            $friendlyMessage = $message;

            if (str_contains($message, "Field 'id' doesn't have a default value")) {
                $errorCode = 'FIELD_ID_NO_DEFAULT';
                $friendlyMessage = "Kolom ID tidak memiliki default value (auto increment / UUID)";
            } elseif (str_contains($message, 'Duplicate entry')) {
                $errorCode = 'DUPLICATE_ENTRY';
                $friendlyMessage = 'Data duplikat';
            } elseif (str_contains($message, 'cannot be null')) {
                $errorCode = 'NULL_VALUE';
                $friendlyMessage = 'Ada field wajib yang kosong';
            }

            $normalized[] = [
                'row'         => $error['row'] ?? null,
                'employee_id' => $error['employee_id'] ?? null,
                'error_code'  => $errorCode,
                'message'     => $friendlyMessage,
            ];
        }

        return $normalized;
    }


    /**
     * Download file
     */
    public function downloadExcel()
    {
        $filePath = Storage::path('public/templates/kpi_company_import_template.xlsx');


        if (file_exists($filePath)) {
            return Response::download($filePath);
        }

        abort(404, 'File not found');
    }
}
