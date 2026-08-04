<?php

namespace App\Imports;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

use App\Models\ApprovalLayer;
use App\Models\Employee;
use App\Models\PerformanceDialog;
use App\Models\PerformanceDialogType;

class PerformanceDialogManagerImport implements ToModel, WithValidation, WithHeadingRow
{
    public $successCount = 0;
    public $errorCount = 0;
    public $userID;
    public $filePath;
    public $data = [];
    public $detailError = [];
    protected $invalidEmployees = [];

    public function __construct($filePath, $userID)
    {
        $this->filePath = $filePath;
        $this->userID = $userID;

        if (empty($this->userID)) {
            $this->userID = Auth::id();
        }
    }

    public function model(array $row)
    {
        Log::info("Processing row: ", $row);

        try {
            static $headersChecked = false;

            if (!$headersChecked) {
                $headers = collect($row)->keys();
                $expectedHeaders = ['employee_id', 'employee_name', 'current_approver_id', 'summary', 'additional_notes', 'development_plan', 'due_date', 'period', 'schedule_date', 'initiate_date', 'type_name', 'status'];

                if (!collect($expectedHeaders)->diff($headers)->isEmpty()) {
                    throw ValidationException::withMessages([
                        'error' => 'Invalid excel format. The header must contain employee_id, employee_name, current_approver_id, summary, additional_notes, development_plan, due_date, period, schedule_date, initiate_date, type_name, status.',
                    ]);
                }

                $headersChecked = true;
            }

            $validate = Validator::make($row, [
                'employee_id' => 'digits:11',
                'current_approver_id' => 'digits:11',
            ]);

            if ($validate->fails()) {
                $errors = $validate->errors();

                if ($errors->has('employee_id')) {
                    $this->detailError[] = [
                        'employee_id' => $row['employee_id'],
                        'message' => "Employee ID must contain 11 digits.",
                    ];
                    $this->invalidEmployees[] = [
                        'employee_id' => $row['employee_id'],
                        'message' => "Employee ID must contain 11 digits.",
                    ];
                }

                if ($errors->has('current_approver_id')) {
                    $this->detailError[] = [
                        'employee_id' => $row['current_approver_id'],
                        'message' => "Current Approver ID must contain 11 digits.",
                    ];
                    $this->invalidEmployees[] = [
                        'employee_id' => $row['current_approver_id'],
                        'message' => "Current Approver ID must contain 11 digits.",
                    ];
                }
            }

            $employeeID = $row['employee_id'];
            $currentApproverID = $row['current_approver_id'];
            $summary = $row['summary'];
            $developmentPlan =  $row['development_plan'];
            $additionalNotes =  $row['additional_notes'];
            $period =  $row['period'];
            $initiateDate = Carbon::now();
            $startDate = $row['schedule_date'];
            $endDate = null;
            $dueDate = $row['due_date'];
            $typeName = "";
            $status = $row['status'];
            $typeIDs = [];
            $othersTypeName = null;

            if (isset($row['objectives'])) {
                $typeName = $row['objectives'];
            } else if (isset($row['type_name'])) {
                $typeName = $row['type_name'];
            }

            $typeNames = explode(',', $typeName);
            $typeNames = array_map('trim', $typeNames);
            $typeNames = array_filter($typeNames, function($value) {
                return $value !== '';
            });
            $typeNames = array_values($typeNames);

            if (isset($row['initiate_date']) && !empty($row['initiate_date'])) {
                $initiateDate = $row['initiate_date'];
            }

            if ($status && $status != "Done") {
                $initiateDate = null;
            }

            if (empty($period)) {
                $period = now()->year;
            }

            if (empty($status)) {
                $status = "Pending";
            }

            $employeeExist = Employee::where('employee_id', $employeeID)->exists();
            if (!$employeeExist) {
                $message = "Employee : " . $row['employee_name'] . " with ID " . $employeeID . " not exist.";

                Log::info($message);

                $this->detailError[] = [
                    'employee_id' => $employeeID,
                    'message' => $message,
                ];
                $this->invalidEmployees[] = [
                    'employee_id' => $employeeID,
                    'message' => $message,
                ];

                $this->errorCount++;
                return;
            }

            $currentApproverExist = Employee::where('employee_id', $currentApproverID)->exists();
            if (!$currentApproverExist) {
                $message = "Current approver with ID " . $currentApproverID . " not exist.";

                Log::info($message);

                $this->detailError[] = [
                    'employee_id' => $employeeID,
                    'current_approver_id' => $currentApproverID,
                    'message' => $message,
                ];
                $this->invalidEmployees[] = [
                    'employee_id' => $employeeID,
                    'message' => $message,
                ];

                $this->errorCount++;
                return;
            }

            $existLayer = ApprovalLayer::where('approver_id', $currentApproverID)->where('employee_id', $employeeID)->max('layer');
            if (!$existLayer) {
                $message = "Cannot find Layer ID : " . $currentApproverID . " on Employee ID: " . $employeeID . ".";

                Log::info($message);

                $this->detailError[] = [
                    'employee_id' => $employeeID,
                    'message' => $message,
                ];
                $this->invalidEmployees[] = [
                    'employee_id' => $employeeID,
                    'message' => $message,
                ];

                $this->errorCount++;
                return;
            }

            $performanceDialogTypes = PerformanceDialogType::whereIn("name", $typeNames)->get();
            $performanceDialogTypesGroupByName = $performanceDialogTypes->groupBy('name');

            foreach ($typeNames as $typeNameRow) {
                if (isset($performanceDialogTypesGroupByName[$typeNameRow]) && $performanceDialogTypesGroupByName[$typeNameRow] && !empty($performanceDialogTypesGroupByName[$typeNameRow])) {
                    $typeIDs[] = $performanceDialogTypesGroupByName[$typeNameRow]->id;
                } else {
                    $othersTypeName = $typeNameRow;
                }
            }

            if (empty($typeIDs)) {
                $typeIDs = null;
            }

            $this->data[] = [
                "employee_id" => $employeeID,
                "manager_employee_id" => $currentApproverID,
                "summary" => $summary,
                "development_plan" => $developmentPlan,
                "additional_notes" => $additionalNotes,
                "period" => $period,
                "initiate_date" => $initiateDate,
                "start_date" => $startDate,
                "end_date" => $endDate,
                "due_date" => $dueDate,
                "type_ids" => $typeIDs,
                "others_type_name" => $othersTypeName,
                "status" => $status,
                "created_by" => $this->userID,
                "created_at" => Carbon::now(),
                "updated_by" => $this->userID,
                "updated_at" => Carbon::now()
            ];
        } catch (\Exception $e) {
            Log::error("Error processing row: " . $e->getMessage());

            $this->detailError[] = [
                'employee_id' => $row['employee_id'] ?? 'Unknown',
                'message' => "Error during import: " . $e->getMessage(),
            ];
            $this->invalidEmployees[] = [
                'employee_id' => $row['employee_id'] ?? 'Unknown',
                'message' => "Error during import: " . $e->getMessage(),
            ];

            $this->errorCount++;
        }
    }

    public function saveToDatabase()
    {
        ksort($this->data, SORT_NUMERIC);

        foreach ($this->data as $data) {
            DB::beginTransaction();

            try {
                Log::info("Preparing to insert data for Employee ID: " . $data['employee_id'], [
                    'data' => $data,
                ]);

                Log::info("Starting transaction for Employee ID: " . $data['employee_id']);

                $PerformanceDialog = DB::table('performance_dialogs')
                    ->where('employee_id', $data['employee_id'])
                    ->where('manager_employee_id', $data['manager_employee_id'])
                    ->where('period', $data['period'])
                    ->where('start_date', $data['start_date'])
                    ->where('end_date', $data['end_date'])
                    ->where('due_date', $data['due_date'])
                    ->where('deleted_at', null)
                    ->first();

                if ($PerformanceDialog) {
                    DB::table('performance_dialogs')
                        ->where('employee_id', $data['employee_id'])
                        ->where('manager_employee_id', $data['manager_employee_id'])
                        ->where('period', $data['period'])
                        ->where('initiate_date', $data['initiate_date'])
                        ->where('start_date', $data['start_date'])
                        ->where('end_date', $data['end_date'])
                        ->where('due_date', $data['due_date'])
                        ->where('deleted_at', null)
                        ->update([
                            'summary' => $data['summary'],
                            'development_plan' => $data['development_plan'],
                            'additional_notes' => $data['additional_notes'],
                            'type_ids' => $data['type_ids'] ? json_encode($data['type_ids']) : null,
                            'others_type_name' => $data['others_type_name'],
                            'status' => $data['status'],
                            'updated_by' => $data['updated_by'],
                            'updated_at' => $data['updated_at'],
                        ]);
                } else {
                    DB::table('performance_dialogs')->insert([
                        'manager_employee_id' => $data['manager_employee_id'],
                        'employee_id' => $data['employee_id'],
                        'period' => $data['period'],
                        'summary' => $data['summary'],
                        'development_plan' => $data['development_plan'],
                        'additional_notes' => $data['additional_notes'],
                        'initiate_date' => $data['initiate_date'],
                        'start_date' => $data['start_date'],
                        'end_date' => $data['end_date'],
                        'due_date' => $data['due_date'],
                        'type_ids' => $data['type_ids'] ? json_encode($data['type_ids']) : null,
                        'others_type_name' => $data['others_type_name'],
                        'status' => $data['status'],
                        'created_by' => $data['created_by'],
                        'created_at' => $data['created_at'],
                        'updated_by' => $data['updated_by'],
                        'updated_at' => $data['updated_at'],
                    ]);
                }

                DB::commit();

                $this->successCount++;

                Log::info("Data inserted for Employee ID: " . $data['employee_id']);

            } catch (\Exception $e) {
                DB::rollBack();

                Log::error("Error inserting data for Employee ID: " . $data['employee_id'] . ". Error: " . $e->getMessage());

                $this->detailError[] = [
                    'employee_id' => $data['employee_id'],
                    'message' => "Error during import: " . $e->getMessage(),
                ];
                $this->invalidEmployees[] = [
                    'employee_id' => $data['employee_id'],
                    'message' => "Error during import: " . $e->getMessage(),
                ];

                $this->errorCount++;
            }
        }
    }

    public function rules(): array
    {
        Log::info("Validating Excel data 2...");

        return [
            'employee_id' => 'required|string',
            'employee_name' => 'required|string',
            'current_approver_id' => 'required|string',
            'status' => 'required|string',
        ];
    }

    public function saveTransaction()
    {
        $filePathWithoutPublic = str_replace('public/', '', $this->filePath);

        DB::table('performance_dialog_import_transactions')->insert([
            'success' => $this->successCount,
            'error' => $this->errorCount,
            'detail_error' => $this->detailError ? json_encode($this->detailError) : null,
            'file_uploads' => $filePathWithoutPublic,
            'submit_by' => $this->userID,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function getInvalidEmployees()
    {
        return $this->invalidEmployees;
    }
}
