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
use App\Services\AppService;
use App\Imports\PerformanceDialogManagerImport;

class PerformanceDialogTaskController extends Controller
{
    protected $loggedInUser;
    protected $appService;

    public function __construct(AppService $appService)
    {
        $this->loggedInUser = Auth::user();
        $this->appService = $appService;
    }

    public function index(Request $request) {
        $userID = $this->loggedInUser->id;
        $employeeID = $this->loggedInUser->employee_id;
        $period = $this->appService->appraisalPeriod();

        if (!empty($request->period)) {
            $period = $request->period;
        }

        $performanceDialogs = PerformanceDialog::with(['employeeCreatedBy', 'employeeUpdatedBy'])
            ->where('manager_employee_id', $employeeID)
            ->where('period', $period)
            ->where('deleted_at', null)
            ->get();

        $performanceDialogYears = PerformanceDialog::select('period')
            ->where('manager_employee_id', $employeeID)
            ->distinct()
            ->orderBy('period')
            ->pluck('period');

        if (empty($performanceDialogYears)) {
            $performanceDialogYears = [$period];
        }

        return view('pages.performance-dialog.task-box', [
            "parentLink" => "Performance Dialog",
            "link" => "Task Box",
            "period" => $period,
            "user_id" => $userID,
            "employee_id" => $employeeID,
            "performance_dialogs" => $performanceDialogs,
            "performance_dialog_years" => $performanceDialogYears,
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

                $message = 'Some of import data failed! <a href="' . route('performance-dialog-task.export-invalid') . '"><u>Click here to download the list of errors.</u></a>';

                return redirect()->back()->with('error', $message);
            }

            $queries = DB::getQueryLog();

            Log::info($userID ." Executed queries import goals manager: ", $queries);
            Log::info($userID ." Performance Dialog import : Data imported successfully.");

            return redirect()->back()->with('success', $message);
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', $e->errors()['error'][0]);
        } catch (\Exception $e) {
            $errorMessage = "Import failed: " . $e->getMessage();

            Log::error($userID . " " . $errorMessage);

            return back()->with('error', $errorMessage);
        }
    }
}
