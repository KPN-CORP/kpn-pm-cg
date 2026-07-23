<?php

namespace App\Imports;

use Exception;
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
use App\Models\PerformanceDialogType;

class GoalsDataImport implements ToModel, WithValidation, WithHeadingRow
{
    public $successCount = 0;
    public $errorCount = 0;
    public $filePath;
    public $data = [];
    public $detailError = [];

    public function __construct($filePath)
    {
        $this->filePath = $filePath;
    }

    public function model(array $row)
    {
        Log::info("Processing row: ", $row);

        try {
            static $headersChecked = false;

            if (!$headersChecked) {
                $headers = collect($row)->keys();
                $expectedHeaders = ['employee_id', 'employee_name', 'current_approver_id', 'summary', 'development_plan', 'additional_notes', 'period', 'start_date', 'end_date', 'due_date', 'type_name', 'status'];

                if (!collect($expectedHeaders)->diff($headers)->isEmpty()) {
                    throw ValidationException::withMessages([
                        'error' => 'Invalid excel format. The header must contain employee_id, employee_name, current_approver_id, summary, development_plan, additional_notes, period, start_date, end_date, due_date, type_name, status.',
                    ]);
                }

                $headersChecked = true;
            }

            $validate = Validator::make($row, [
                'employee_id' => 'digits:11',
                'weightage' => 'required|numeric|min:0.05|max:1.00'
            ]);

            if ($validate->fails()) {
                $errors = $validate->errors();

                if ($errors->has('employee_id')) {
                    $this->detailError[] = [
                        'employee_id' => $row['employee_id'],
                        'message' => "Employee ID must contain 11 digits.",
                    ];
                }
            }

            $employeeID = $row['employee_id'];
            $currentApproverID = $row['current_approver_id'];
            $summary = $row['summary'];
            $developmentPlan =  $row['development_plan'];
            $additionalNotes =  $row['additional_notes'];
            $period =  $row['period'];
            $startDate = $row['start_date'];
            $endDate = $row['end_date'];
            $dueDate = $row['due_date'];
            $typeName = $row['type_name'];
            $status = $row['status'];
            $typeIDs = null;
            $othersTypeName = null;

            if (empty($period)) {
                $period = now()->year;
            }

            $employeeExist = Employee::where('employee_id', $employeeID)->exists();
            if (!$employeeExist) {
                $message = "Employee : " . $row['employee_name'] . " with ID " . $employeeId . " not exist.";

                Log::info($message);

                $this->detailError[] = [
                    'employee_id' => $employeeId,
                    'message' => $message,
                ];

                $this->errorCount++;
                return;
            }

            $performanceDialogType = PerformanceDialogType::where("name", $typeName)->first();
            if (!empty($performanceDialogType)) {
                $typeIDs[] = $performanceDialogType->id;
            } else {
                $othersTypeName = $typeName;
            }

            $this->data[] = [
                "manager_employee_id" => $currentApproverID,
                "employee_id" => $employeeID,
                "summary" => $summary,
                "development_plan" => $developmentPlan,
                "additional_notes" => $additionalNotes,
                "period" => $period,
                "start_date" => $startDate,
                "end_date" => $endDate,
                "due_date" => $dueDate,
                "type_ids" => $typeIDs,
                "others_type_name" => $othersTypeName,
                "status" => $status,
                "created_by" => "",
                "created_at" => Carbon::now(),
                "updated_by" => "",
                "updated_at" => Carbon::now()
            ];
        } catch (\Exception $e) {
            Log::error("Error processing row: " . $e->getMessage());

            $this->detailError[] = [
                'employee_id' => $row['employee_id'] ?? 'Unknown',
                'message' => "Error during import: " . $e->getMessage(),
            ];

            $this->errorCount++;
        }
    }

    public function saveToDatabase()
    {
        ksort($this->employeesData, SORT_NUMERIC);

        foreach ($this->employeesData as $employeeId => $data) {

            DB::beginTransaction();

            try {

                $existLayer = ApprovalLayer::where('approver_id', $data['current_approval_id'])
                    ->where('employee_id', $employeeId)->max('layer');

                if (!$existLayer) {
                    $message = "Cannot find Layer ID : " . $data['current_approval_id'] . " on Employee ID: $employeeId.";
                    Log::info($message);

                    $this->detailError[] = [
                        'employee_id' => $employeeId,
                        'message' => $message,
                    ];

                    $this->errorCount++;
                    DB::rollBack();
                    continue;
                }

                $existsInAppraisals = Appraisal::where('employee_id', $employeeId)
                    ->where('period', $data['period'])
                    ->exists();

                if ($existsInAppraisals) {
                    $message = "Employee ID: $employeeId already has appraisal data.";
                    Log::info($message);

                    $this->detailError[] = [
                        'employee_id' => $employeeId,
                        'message' => $message,
                    ];

                    $this->errorCount++;
                    DB::rollBack();
                    continue;
                }

                $totalWeightage = 0;

                // Decode form_data if it's a JSON string
                $formData = is_string($data['form_data']) ? json_decode($data['form_data'], true) : $data['form_data'];

                if (!is_array($formData)) {
                    continue; // Skip if form_data is not valid
                }

                // Loop through each KPI entry and sum the weightage
                foreach ($formData as $entry) {
                    $totalWeightage += (float) ($entry['weightage'] ?? 0); // Ensure float conversion
                }

                // Validate if total weightage is exactly 100
                if ($totalWeightage !== 100.0) {
                    $message = "Total weightage for Employee ID $employeeId must be 100%. Current total: $totalWeightage%";
                    Log::info($message);

                    $this->detailError[] = [
                        'employee_id' => $employeeId,
                        'message' => $message,
                    ];

                    $this->errorCount++;
                    DB::rollBack();
                    continue;
                }

                $formId = Str::uuid();

                Log::info("Preparing to insert data for Employee ID: " . $employeeId, [
                    'form_data' => $data['form_data'],
                ]);

                Log::info("Starting transaction for Employee ID: " . $employeeId);

                Log::info("Deleting old data for Employee ID: " . $employeeId);
                DB::table('goals')
                    ->where('employee_id', $employeeId)
                    ->where('category', $data['category'])
                    ->where('period', $data['period'])
                    ->update(['deleted_at' => now()]);
                Log::info("Old data deleted for Employee ID: " . $employeeId);

                Log::info("Data for Employee ID: " . $employeeId, $data);
                DB::table('goals')->insert([
                    'id' => $formId,
                    'employee_id' => $employeeId,
                    'category' => $data['category'],
                    'form_data' => json_encode($data['form_data']),
                    'form_status' => 'Approved',
                    'period' => $data['period'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                Log::info("Old goals updated for Employee ID: " . $employeeId);


                $approvalRequests = DB::table('approval_requests')
                    ->where('employee_id', $employeeId)
                    ->where('category', $data['category'])
                    ->where('period', $data['period'])
                    ->pluck('id'); // Get the list of IDs

                // Soft delete approvals linked to these requests
                DB::table('approvals')
                    ->whereIn('request_id', $approvalRequests) // Use the retrieved IDs
                    ->update(['deleted_at' => now()]);

                // Soft delete approval_requests
                DB::table('approval_requests')
                    ->whereIn('id', $approvalRequests)
                    ->update(['deleted_at' => now()]);

                $empId = Employee::where('employee_id', $employeeId)->pluck('id')->first();

                if ($empId) {
                    Log::info("EmployeeAppraisal ID found for Employee ID: " . $employeeId . ". EmpId: " . $empId);
                } else {
                    Log::error("No EmployeeAppraisal record found for Employee ID: " . $employeeId);
                }

                $requestId = DB::table('approval_requests')->insertGetId([
                    'form_id' => $formId,  // Gunakan UUID yang sama
                    'category' => 'Goals',
                    'current_approval_id' => $data['current_approval_id'],
                    'employee_id' => $employeeId,
                    'status' => 'Approved',
                    'messages' => 'import by admin',
                    'period' => $data['period'],
                    'created_by' => $empId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Use the request ID in the approvals table
                DB::table('approvals')->insert([
                    'request_id' => $requestId,  // Use the stored ID
                    'approver_id' => $data['current_approval_id'],
                    'status' => 'Approved',
                    'created_by' => $empId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();

                $this->successCount++;
                Log::info("Data inserted for Employee ID: " . $employeeId);

            } catch (\Exception $e) {
                DB::rollBack();

                Log::error("Error inserting data for Employee ID: " . $employeeId . ". Error: " . $e->getMessage());

                $this->errorCount++;
                $this->detailError[] = [
                    'employee_id' => $employeeId,
                    'message' => "Error during import: " . $e->getMessage(),
                ];
            }
        }
    }

    public function rules(): array
    {
        Log::info("Validating Excel data 2...");
        return [
            'employee_id' => 'required|string',
            'employee_name' => 'required|string',
            'category' => 'required|string',
            'kpi' => 'required|string',
            'target' => 'required|numeric',
            'uom' => 'required|string',
            'weightage' => 'required|numeric',
            'type' => 'required|string',
        ];
    }

    public function saveTransaction()
    {
        $filePathWithoutPublic = str_replace('public/', '', $this->filePath);
        DB::table('goals_import_transactions')->insert([
            'success' => $this->successCount,
            'error' => $this->errorCount,
            'detail_error' => $this->detailError ? json_encode($this->detailError) : null,
            'file_uploads' => $filePathWithoutPublic,
            'submit_by' => Auth::id(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
