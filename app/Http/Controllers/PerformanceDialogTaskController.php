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
use App\Services\AppService;
use App\Imports\PerformanceDialogManagerImport;
use App\Exports\InvalidPerformanceDialogManagerImport;

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
        $period = now()->year;

        if (!empty($request->period)) {
            $period = $request->period;
        }

        if (!empty($request->filterYear)) {
            $period = $request->filterYear;
        }

        $rows = [];

        $performanceDialogs = PerformanceDialog::with(['employee'])
            ->where('manager_employee_id', $employeeID)
            ->where('period', $period)
            ->where('deleted_at', null)
            ->get();

        $now = Carbon::now();

        foreach($performanceDialogs as $row) {
            $row->formatted_start_date = $row->start_date ? Carbon::parse($row->start_date)->format('d M Y') : '-';
            $row->formatted_end_date = $row->end_date ? Carbon::parse($row->end_date)->format('d M Y') : '-';
            $row->formatted_due_date = $row->due_date ? Carbon::parse($row->due_date)->format('d M Y') : '-';
            $row->formatted_created_at = $row->created_at ? Carbon::parse($row->created_at)->format('d M Y') : '-';
            $row->formatted_updated_at = $row->updated_at ? Carbon::parse($row->updated_at)->format('d M Y') : '-';

            $dueDate = $row->due_date ?? "-";
            $scheduleAt = $row->start_date ?? "-";
            $initiatedAt = $row->created_at ?? "-";
            $status = $row->status ?? "-";
            $isActionInitiate = false;
            $isActionEdit = false;
            $isActionDownload = false;

            if ($dueDate != "-" && Carbon::parse($dueDate)->lt($now)) {
                $status = "Overdue";
            }

            if ($status == "Scheduled" || $status == "Overdue") {
                $isActionInitiate = true;
            }

            if ($status == "Draft" || $status == "Submitted") {
                $isActionEdit = true;
            }

            if ($status == "Done" || $status == "Submitted") {
                $isActionDownload = true;
            }

            $formattedScheduleAt = $scheduleAt != "-" ? Carbon::parse($scheduleAt)->format('d M Y') : '-';
            $formattedInitiatedAt = $initiatedAt != "-" ? Carbon::parse($initiatedAt)->format('d M Y') : '-';

            $rows[] = [
                "id" => $row->id,
                "employee_id" => $row->employee_id,
                "employee_name" => $row->employee?->fullname ?? "-",
                "formatted_schedule_at" => $formattedScheduleAt,
                "formatted_initiated_at" => $formattedInitiatedAt,
                "status" => $status,
                "is_action_initiate" => $isActionInitiate,
                "is_action_schedule" => false,
                "is_action_edit" => $isActionEdit,
                "is_action_download" => $isActionDownload
            ];
        }

        $performanceDialogGroupByEmployeeID = $performanceDialogs->groupBy('employee_id');

        $performanceDialogYears = PerformanceDialog::select('period')
            ->where('manager_employee_id', $employeeID)
            ->distinct()
            ->orderBy('period')
            ->pluck('period');

        if ($performanceDialogYears->isEmpty()) {
            $performanceDialogYears = collect([$period]);
        }

        $reportees = ApprovalLayer::with(["employee"])->where("approver_id", $employeeID)->get();

        foreach($reportees as $reportee) {
            $reporteePerformanceDialog = $performanceDialogGroupByEmployeeID[$reportee->employee_id] ?? null;

            if ($reporteePerformanceDialog) {
                continue;
            }

            $rows[] = [
                "id" => null,
                "employee_id" => $reportee->employee_id,
                "employee_name" => $reportee->employee?->fullname ?? "-",
                "formatted_schedule_at" => "-",
                "formatted_initiated_at" => "-",
                "status" => "Not Scheduled",
                "is_action_initiate" => true,
                "is_action_schedule" => true,
                "is_action_edit" => false,
                "is_action_download" => false
            ];
        }

        return view('pages.performance-dialog.task-box', [
            "parentLink" => "Performance Dialog",
            "link" => "Task Box",
            "period" => $period,
            "user_id" => $userID,
            "employee_id" => $employeeID,
            "performance_dialog_years" => $performanceDialogYears,
            "rows" => $rows,
            "reportees" => $reportees,
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

                $message = 'Some of import data failed! <a href="' . route('performance-dialog-task.invalid-export') . '"><u>Click here to download the list of errors.</u></a>';

                return redirect()->back()->with('error', $message)->with('error_client', 'Some of import data failed!');
            }

            $queries = DB::getQueryLog();

            Log::info($userID ." Executed queries import goals manager: ", $queries);
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
