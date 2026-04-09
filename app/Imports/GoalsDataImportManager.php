<?php
namespace App\Imports;

use App\Exports\InvalidGoalImport;
use App\Models\Appraisal;
use App\Models\ApprovalLayer;
use App\Models\Employee;
use App\Models\EmployeeAppraisal;
use App\Models\Goal;
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
use Maatwebsite\Excel\Facades\Excel;

class GoalsDataImportManager implements ToModel, WithValidation, WithHeadingRow
{
    public $successCount = 0; // Hitungan data berhasil
    public $errorCount = 0;   // Hitungan data gagal
    public $filePath;
    public $userId;
    public $period;
    public $category;
    public $employeesData = []; // Untuk menyimpan semua data berdasarkan employee_id
    public $detailError = [];
    protected $invalidEmployees = [];

    public function __construct($filePath, $userId, $period)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
        $this->period = $period;
        $this->category = "Goals";
    }

    /**
     * Mapping data Excel ke model.
     */
    public function model(array $row)
    {
        Log::info("Processing imports: ", $row);

        try {

            static $headersChecked = false;

            if (!$headersChecked) {
                $headers = collect($row)->keys();
                $expectedHeaders = ['employee_id', 'kpi', 'target', 'uom', 'weightage', 'type'];

                if (!collect($expectedHeaders)->diff($headers)->isEmpty()) {
                    throw ValidationException::withMessages([
                        'error' => 'Invalid excel format. The header must contain Employee_ID, KPI, Target, UOM, Weightage, Type, Description.',
                    ]);
                }

                $headersChecked = true;
            }

            $nextLayer = ApprovalLayer::where('approver_id', $this->userId)
                                    ->where('employee_id', $row['employee_id'])->max('layer');

            if ($nextLayer === null) {
                $this->invalidEmployees[] = [
                    'employee_id' => $row['employee_id'],
                    'message' => "Employee ID: $row[employee_id] is not under your approval layer.", // Get the first error message
                ];
            }

            // Cari approver_id pada layer selanjutnya
            $nextApprover = ApprovalLayer::where('layer', $nextLayer + 1)->where('employee_id', $row['employee_id'])->value('approver_id');

            if (!$nextApprover) {
                $approver = $this->userId;
                $statusRequest = 'Approved';
                $statusForm = 'Approved';
            }else{
                $approver = $nextApprover;
                $statusRequest = 'Pending';
                $statusForm = 'Submitted';
            }

            $validate = Validator::make($row, [
                'employee_id' => 'digits:11', // Ensure employee_id is exactly 11 digits
                'weightage' => 'required|numeric'
            ]);

            if ($validate->fails()) {
                $errors = $validate->errors(); // Get validation errors

                // Check if 'employee_id' has errors
                if ($errors->has('employee_id')) {
                    $this->detailError[] = [
                        'employee_id' => $row['employee_id'],
                        'message' => "Employee ID must contain 11 digits.",
                    ];
                    $this->invalidEmployees[] = [
                        'employee_id' => $row['employee_id'],
                        'message' => "Employee ID must contain 11 digits.", // Get the first error message
                    ];
                    return;
                }

                // Check if 'weightage' has errors
                if ($errors->has('weightage')) {
                    $this->detailError[] = [
                        'employee_id' => $row['employee_id'],
                        'message' => "Weightage must be in percent.",
                    ];
                    $this->invalidEmployees[] = [
                        'employee_id' => $row['employee_id'],
                        'message' => "Weightage must be in percent.", // Separate messages
                    ];
                    return;
                }
            }

            $path = base_path('resources/goal.json');

            // Check if the JSON file exists
            if (!File::exists($path)) {
                abort(500, 'JSON file does not exist.');
            }

            // Read the contents of the JSON file
            $options = json_decode(File::get($path), true);

            $uomOption = $options['UoM'];

            $validUoms = collect($uomOption)->flatten()->all();

            if (in_array($row['uom'], $validUoms, true)) {
                $uom = $row['uom'];
                $custom_uom = null;
            }else{
                $uom = "Other";
                $custom_uom = $row['uom'];
            }

            $status = 'Approved';

            $employeeId = $row['employee_id'];

            // Simpan data KPI ke array berdasarkan employee_id
            if (!isset($this->employeesData[$employeeId])) {
                $this->employeesData[$employeeId] = [
                    'category' => $this->category,
                    'form_data' => [],
                    'current_approval_id' => $approver,  // Menyimpan langsung current_approval_id
                    'status' => $status,  // Menyimpan langsung current_approval_id
                    'status_request' => $statusRequest,  // Menyimpan langsung current_approval_id
                    'form_status' => $statusForm,  // Menyimpan langsung current_approval_id
                    'period' => $this->period,  // Menyimpan langsung period
                ];
            }

            // Tambahkan data KPI ke form_data
            $this->employeesData[$employeeId]['form_data'][] = [
                'kpi' => $row['kpi'],
                'target' => $row['target'],
                'uom' => $uom,
                'weightage' => $row['weightage'] * 100, // Convert to percentage
                'description' => $row['description'],
                'type' => $row['type'],
                'custom_uom' => $custom_uom,
            ];

        } catch (\Exception $e) {
            Log::error("Error processing row: " . $e->getMessage());
            $this->errorCount++;
            $this->detailError[] = $row['employee_id'];
            $this->invalidEmployees[] = [
                'employee_id' => $row['employee_id'],
                'message' => $e->getMessage(), // Get the first error message
            ];
        }
    }

    public function saveToDatabase()
    {
        ksort($this->employeesData, SORT_NUMERIC);

        foreach ($this->employeesData as $employeeId => $data) {

            DB::beginTransaction();

            try {

                $employeeExist = Employee::where('employee_id', $employeeId)
                    ->exists();

                if (!$employeeExist) {
                    $message = "Employee ID: $employeeId not exist.";
                    Log::info($message);

                    $this->detailError[] = [
                        'employee_id' => $employeeId,
                        'message' => $message,
                    ];

                    $this->invalidEmployees[] = [
                        'employee_id' => $employeeId,
                        'message' => $message, // Get the first error message
                    ];

                    DB::rollBack();
                    continue;
                }

                $existsInAppraisals = Appraisal::where('employee_id', $employeeId)
                    ->where('period', $data['period'])
                    ->exists();

                $existsInGoals = Goal::where('employee_id', $employeeId)
                    ->where('period', $data['period'])
                    ->exists();

                if ($existsInGoals) {
                    $message = "Employee ID: $employeeId already has goals data.";
                    Log::info($message);

                    $this->detailError[] = [
                        'employee_id' => $employeeId,
                        'message' => $message,
                    ];

                    $this->invalidEmployees[] = [
                        'employee_id' => $employeeId,
                        'message' => $message, // Get the first error message
                    ];

                    $this->errorCount++;
                    DB::rollBack();
                    continue;
                }

                if ($existsInAppraisals) {
                    $message = "Employee ID: $employeeId already has appraisal data.";
                    Log::info($message);

                    $this->detailError[] = [
                        'employee_id' => $employeeId,
                        'message' => $message,
                    ];

                    $this->invalidEmployees[] = [
                        'employee_id' => $employeeId,
                        'message' => $message, // Get the first error message
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

                foreach ($formData as $entry) {
                    $weightage = (float)($entry['weightage']); // Ensure float conversion

                    if ($weightage < 5.0 || $weightage > 100.0) {
                        $message = "Weightage must be minimum 5% and maximum 100%.";
                        Log::info($message);

                        $this->detailError[] = [
                            'employee_id' => $employeeId,
                            'message' => $message,
                        ];

                        $this->invalidEmployees[] = [
                            'employee_id' => $employeeId,
                            'message' => $message, // Separate messages
                        ];

                        $this->errorCount++;
                        DB::rollBack();
                        continue 2; // Skip to the next employee
                    }

                    $totalWeightage += $weightage; // Sum the weightage
                }

                // Validate if total weightage is exactly 100
                if ($totalWeightage !== 100.0) {
                    $message = "Total weightage for Employee ID $employeeId must be 100%. Current total: $totalWeightage%";
                    Log::info($message);

                    $this->detailError[] = [
                        'employee_id' => $employeeId,
                        'message' => $message,
                    ];

                    $this->invalidEmployees[] = [
                        'employee_id' => $employeeId,
                        'message' => $message, // Get the first error message
                    ];

                    $this->errorCount++;
                    DB::rollBack();
                    continue;
                }

                $formId = Str::uuid();

                Log::info("Preparing to insert data for Employee ID: " . $employeeId, [
                    'form_data' => $data['form_data'],
                ]);

                Log::info("Starting imports transaction for Employee ID: " . $employeeId);

                DB::table('goals')->insert([
                    'id' => $formId,
                    'employee_id' => $employeeId,
                    'category' => $data['category'],
                    'form_data' => json_encode($data['form_data']),
                    'form_status' => $data['form_status'],
                    'period' => $data['period'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                Log::info("New goals added for Employee ID: " . $employeeId);

                $empId = EmployeeAppraisal::where('employee_id', $employeeId)->pluck('id')->first();

                if ($empId) {
                    Log::info("EmployeeAppraisal ID found for Employee ID: " . $employeeId . ". EmpId: " . $empId);
                } else {
                    Log::error("No EmployeeAppraisal record found for Employee ID: " . $employeeId);
                }

                // Insert into approval_requests and get the ID
                $requestId = DB::table('approval_requests')->insertGetId([
                    'form_id' => $formId,  // Gunakan UUID yang sama
                    'category' => 'Goals',
                    'current_approval_id' => $data['current_approval_id'],
                    'employee_id' => $employeeId,
                    'status' => $data['status_request'],
                    'messages' => 'import by Manager',
                    'period' => $data['period'],
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Use the request ID in the approvals table
                DB::table('approvals')->insert([
                    'request_id' => $requestId,  // Use the stored ID
                    'approver_id' => $this->userId,
                    'status' => 'Approved',
                    'created_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $this->successCount++;
                Log::info("Data inserted for Employee ID: " . $employeeId);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                // Define the error message explicitly
                $errorMessage = "Error during import for Employee ID $employeeId: " . $e->getMessage();

                Log::error($errorMessage);

                $this->errorCount++;
                $this->detailError[] = [
                    'employee_id' => $employeeId,
                    'message' => $errorMessage,
                ];
                $this->invalidEmployees[] = [
                    'employee_id' => $employeeId,
                    'message' => $errorMessage,
                ];
            }
        }
    }

    public function rules(): array
    {
        Log::info("Validating Imports data ...");
        return [
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

    // Return the list of invalid employees with error messages
    public function getInvalidEmployees()
    {
        return $this->invalidEmployees;
    }
}
