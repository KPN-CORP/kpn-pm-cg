<?php

namespace App\Imports;

use App\Exports\InvalidAppraisalRatingImport;
use App\Models\Appraisal;
use App\Models\Calibration;
use App\Models\MasterRating;
use App\Services\AppService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Facades\Excel;

class AdminRatingImport implements ToCollection, WithHeadingRow
{
    protected $userId;
    protected $invalidEmployees = [];
    protected $usedCombinations = [];
    protected $invalidEmployeeIds = [];
    protected $employeeIds = [];
    protected $appService;
    protected $period;
    protected $allowedRating = [];

    public function __construct($userId, $allowedRating, $period)
    {
        $this->userId = $userId;
        $this->allowedRating = $allowedRating;
        $this->appService = new AppService();
        $this->period = $period;
    }

    public function collection(Collection $collection)
    {
        // Validate headers (keeping existing validation)
        $headers = $collection->first()->keys();
        $expectedHeaders = ['Employee_ID', 'Approver_Rating_ID', 'Your_Rating'];
        if (collect($expectedHeaders)->diff($headers)->isEmpty()) {
            throw ValidationException::withMessages([
                'error' => 'Invalid excel format. The header must contain Employee_ID, Approver_Rating_ID, Your_Rating'
            ]);
        }

        $firstConvertedValue = null;

        $isRatingValid = true;
        $totalRows = $collection->count();

        // Initialize rating counts
        $ratingCounts = [
            5 => 0, // firstRow (A)
            4 => 0, // secondRow (B)
            3 => 0, // thirdRow (C)
            2 => 0, // fourthRow (D)
            1 => 0  // fifthRow (E)
        ];

        // First pass: Count ratings and validate basic rules
        foreach ($collection as $index => $row) {
            // Skip invalid ratings
            if (!in_array($row['your_rating'], $this->allowedRating)) {
                $this->invalidEmployees[] = [
                    'employee_id' => $row['employee_id'],
                    'approver_id' => $row['approver_rating_id'],
                    'your_rating' => $row['your_rating'],
                    'message' => 'Your Rating type not found.'
                ];
                $this->invalidEmployeeIds[] = $row['employee_id'];
                $isRatingValid = false;
                continue;
            }

            $calibration = Calibration::with(['masterCalibration'])
                ->where('employee_id', $row['employee_id'])
                ->where('approver_id', $row['approver_rating_id'])
                ->where('status', 'Pending')
                ->first();

            if (!$calibration) continue;

            $id_rating = $calibration->masterCalibration->first()->id_rating_group;
            $ratings = MasterRating::select('parameter', 'value')
                ->where('id_rating_group', $id_rating)
                ->get();

            $ratingMap = $ratings->pluck('value', 'parameter')->toArray();
            $convertedValue = $ratingMap[$row['your_rating']] ?? null;

            // Count each rating
            if ($convertedValue) {
                $ratingCounts[$convertedValue]++;
            }

            if ($index === 0) {
                $firstConvertedValue = $convertedValue;
            }
        }

        // Second pass: Validate quota rules
        if ($isRatingValid) {
            foreach ($collection as $index => $row) {
                if (in_array($row['employee_id'], $this->invalidEmployeeIds)) continue;

                $calibration = $this->getCalibrationsWithRating($row);
                if (!$calibration) continue;

                $convertedValue = $this->getConvertedValue($calibration, $row['your_rating']);
                if (!$convertedValue) continue;

            }
        }

        // Process valid records
        if ($isRatingValid) {
            $this->processValidRecords($collection);
        }
    }

    // Helper methods (add these to your class)
    private function getCalibrationsWithRating($row)
    {
        return Calibration::with(['masterCalibration'])
            ->where('employee_id', $row['employee_id'])
            ->where('approver_id', $row['approver_rating_id'])
            ->where('status', 'Pending')
            ->first();
    }

    private function getConvertedValue($calibration, $rating)
    {
        $id_rating = $calibration->masterCalibration->first()->id_rating_group;
        $ratings = MasterRating::select('parameter', 'value')
            ->where('id_rating_group', $id_rating)
            ->get();
        $ratingMap = $ratings->pluck('value', 'parameter')->toArray();
        return $ratingMap[$rating] ?? null;
    }

    private function processValidRecords(Collection $collection)
    {

        // dd($collection);
        foreach ($collection as $row) {
            // dd($row);
            // Skip invalid employee IDs
            if (in_array($row['employee_id'], $this->invalidEmployeeIds)) {
                continue;
            }

            // Get calibration data
            $calibration = Calibration::with(['masterCalibration'])
                ->where('employee_id', $row['employee_id'])
                ->orderBy('created_at', 'desc')
                ->where('period', $this->period)
                ->first();

            if (!$calibration) {
                continue;
            }

            // Get converted rating value
            $id_rating = $calibration->masterCalibration->first()->id_rating_group;
            $ratings = MasterRating::select('parameter', 'value')
                ->where('id_rating_group', $id_rating)
                ->get();

            $ratingMap = $ratings->pluck('value', 'parameter')->toArray();
            $convertedValue = $ratingMap[$row['your_rating']] ?? null;

            if (!$convertedValue) {
                continue;
            }

            try {
                // Start transaction
                DB::beginTransaction();

                if ($calibration->approver_id == $row['approver_rating_id']) {
                    // Update Calibration
                    $updated = Calibration::where('employee_id', $row['employee_id'])
                        ->where('approver_id', $calibration->approver_id)
                        ->where('period', $this->period)
                        ->update([
                            'updated_by' => Auth::id(),
                            'rating' => $convertedValue,
                            'status' => 'Approved'
                        ]);
                } else {
                    $updated = Calibration::where('employee_id', $row['employee_id'])
                        ->where('approver_id', $calibration->approver_id)
                        ->where('period', $this->period)
                        ->update([
                            'updated_by' => Auth::id()
                        ]);
                    // Now, soft delete the updated record
                    Calibration::where('employee_id', $row['employee_id'])
                        ->where('approver_id', $calibration->approver_id)
                        ->where('period', $this->period)
                        ->delete(); // Soft delete
                }

                // Final approval - update Appraisal
                $appraisal = Appraisal::where('id', $calibration->appraisal_id)
                ->update([
                    'rating' => $convertedValue,
                    'form_status' => 'Approved',
                    'updated_by' => Auth::id()
                ]);

                if ($calibration->approver_id != $row['approver_rating_id']) {
                    // Create new calibration for next approver
                    $createCalibration = new Calibration([
                        'id_calibration_group' => $calibration->id_calibration_group,
                        'appraisal_id' => $calibration->appraisal_id,
                        'employee_id' => $row['employee_id'],
                        'approver_id' => $row['approver_rating_id'],
                        'rating' => $convertedValue,
                        'status' => 'Approved',
                        'period' => $this->period,
                        'created_by' => Auth::id()
                    ]);
                    $createCalibration->save();

                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();

                // Add to invalid employees with error message
                $this->invalidEmployees[] = [
                    'employee_id' => $row['employee_id'],
                    'approver_id' => $row['approver_rating_id'],
                    'your_rating' => $row['your_rating'],
                    'message' => 'Error processing record: ' . $e->getMessage()
                ];

                $this->invalidEmployeeIds[] = $row['employee_id'];
                continue;
            }
        }
    }

    public function saveInvalidEmployees($filename)
    {
        if (!empty($this->invalidEmployees)) {
            $directory = 'imports/errors/'.$this->userId.'/';

            // Ensure directory exists
            Storage::makeDirectory($directory);

            $filePath = $directory.'errors_'.$filename.'.xlsx';

            // Store the file using Storage facade
            Excel::store(new InvalidAppraisalRatingImport($this->invalidEmployees), $filePath, 'public');

            // Return the file URL for downloading
            return [
                'file_name' => 'errors_' . $filename . '.xlsx',
                'file_path' => $filePath
            ];
        }

        return null;
    }

    public function getInvalidEmployees()
    {
        return $this->invalidEmployees;
    }
}
