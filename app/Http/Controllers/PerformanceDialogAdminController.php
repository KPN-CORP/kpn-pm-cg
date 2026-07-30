<?php

namespace App\Http\Controllers;

use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

use App\Models\PerformanceDialog;
use App\Models\ApprovalLayer;
use App\Models\Employee;
use App\Services\AppService;
use App\Imports\PerformanceDialogManagerImport;
use App\Exports\InvalidPerformanceDialogManagerImport;

class PerformanceDialogAdminController extends Controller
{
    protected $loggedInUser;
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->loggedInUser = Auth::user();
        $this->appService = $appService;
    }

    public function importPage(Request $request) {
        return view('pages.performance-dialog.import-admin', [
            "parentLink" => "Imports",
            "link" => "Performance Dialog",
        ]);
    }

    public function import(Request $request)
    {
        $userID = $this->loggedInUser->id;

        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store($path='public/uploads');
            Log::info("File uploaded successfully: " . $filePath);
        } else {
            Log::error("File upload failed.");
            return back()->with('error', "File upload failed.");
        }

        DB::enableQueryLog();

        try {
            $import = new PerformanceDialogManagerImport($filePath, $userID);

            Excel::import($import, $filePath);

            $import->saveToDatabase();
            $import->saveTransaction();

            $invalidEmployees = $import->getInvalidEmployees();

            $message = 'Data imported successfully.';

            if (!empty($invalidEmployees)) {
                session()->put('invalid_employees', $invalidEmployees);

                $message = 'Some of import data failed! <a href="' . route('performance-dialog-admin.invalid-export') . '"><u>Click here to download the list of errors.</u></a>';

                return redirect()->back()->with('error', $message)->with('error_client', 'Some of import data failed!');
            }

            $queries = DB::getQueryLog();

            Log::info($userID ." Executed queries import performance dialog manager: ", $queries);
            Log::info($userID ." Performance Dialog import : Data imported successfully.");

            return redirect()->back()->with('success', $message);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->errors()[0][0]);
        } catch (\Exception $e) {
            $errorMessage = "Import failed: " . $e->getMessage();

            Log::error($userID . " " . $errorMessage);

            return back()->with('error', $errorMessage);
        }
    }

    public function invalidExport()
    {
        $invalidEmployees = session('invalid_employees');

        if (empty($invalidEmployees)) {
            return redirect()->back()->with('success', 'No invalid employees to export.');
        }

        return Excel::download(new InvalidPerformanceDialogManagerImport($invalidEmployees), 'errors_performance_dialog_import.xlsx');
    }
}
