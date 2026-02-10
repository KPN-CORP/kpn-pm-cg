<?php

namespace App\Jobs;

use App\Exports\AppraisalDetailExport;
use App\Services\AppService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;

class ExportAppraisalDetails implements ShouldQueue
{
    use Dispatchable, Queueable;

    protected array $data;
    protected array $headers;
    protected ?AppService $appService = null;
    protected int $userId;
    protected int $batchSize;
    protected string $jobTrackingId;
    protected $user = null;
    protected $period;

    public $timeout = 3600;

    public function __construct(array $data, array $headers, int $userId, int $batchSize = 100, $period = null)
    {
        $this->data = $data;
        $this->headers = $headers;
        $this->userId = $userId;
        $this->batchSize = $batchSize;
        $this->jobTrackingId = 'export_appraisal_reports_' . $userId;
        $this->period = $period;
    }

    public function handle()
    {
        try {
            ini_set('memory_limit', '1G');
            // Resolve service and user inside the job to avoid serializing them
            if (!$this->appService) {
                $this->appService = app(AppService::class);
            }
            if (!$this->user) {
                $this->user = User::find($this->userId);
            }
        // Job logic here
            $directory = 'exports';
            $temporary = 'temp';
            $filePrefix = 'appraisal_details_' . $this->userId;
            $tempFilePrefix = $this->userId . '_batch';

            $period = $this->period ?? $this->appService->appraisalPeriod();
    
            // List all files in the directory
            $files = Storage::disk('public')->files($directory);
            $temporaryFiles = Storage::disk('public')->files($temporary);
    
    
            // If data count is less than or equal to batch size, create single file
            if (count($this->data) <= $this->batchSize) {
                $fileName = 'exports/appraisal_details_' . $this->userId . '.xlsx';
                
                Log::info($this->userId . ' Creating single Excel file: ' . $fileName);
    
                $export = new AppraisalDetailExport($this->appService, $this->data, $this->headers, $this->user, $period);
    
                // Log details of the export object
                Log::info('AppraisalDetailExport instance created', [
                    'userId' => $this->userId,
                    'dataCount' => count($this->data)
                ]);
    
                Excel::store($export, $fileName, 'public');
    
                return;
            }
    
            // For data exceeding batch size, create multiple files and zip them
            $batches = array_chunk($this->data, $this->batchSize);
            $tempFiles = [];
    
            Log::info( $this->userId . ' Starting export with ' . count($batches) . ' batches');
    
            // Create temp directory if it doesn't exist
            $tempDir = storage_path('app/public/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0777, true);
            }
    
            // Create individual Excel files
            foreach ($batches as $index => $batchData) {
                $batchNumber = $index + 1;
                $tempFileName = "temp/{$this->userId}_batch_{$batchNumber}.xlsx";
                $tempFiles[] = $tempFileName;
    
                Log::info($this->userId . ' Processing batch ' . $batchNumber);
    
                $export = new AppraisalDetailExport($this->appService, $batchData, $this->headers, $this->user, $period);
                Excel::store($export, $tempFileName, 'public');
            }
    
            $folderPath = storage_path('app/public/temp'); // Sesuaikan dengan lokasi file Excel Anda

            // Membaca semua file di folder
            $files = glob($folderPath . '/' . $this->userId . '*.xlsx');

            // Buat Spreadsheet baru untuk gabungan
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $rowStart = 1;
            $headerAdded = false;

            foreach ($files as $file) {
                // Open the Excel file
                $excel = Excel::toArray([], $file);
            
                // Check if the file has data
                if (!empty($excel) && isset($excel[0])) {
                    $data = $excel[0]; // Get the first sheet data
            
                    // Add header from the first file only
                    if (!$headerAdded) {
                        $sheet->fromArray(array_shift($data), null, 'A' . $rowStart); // Add header row
                        $headerAdded = true;
                        $rowStart++;
                    } else {
                        // Remove the header from subsequent files
                        array_shift($data);
                    }
            
                    // Append data rows starting from the first column
                    foreach ($data as $row) {
                        $sheet->fromArray($row, null, 'A' . $rowStart);
                        $rowStart++;
                    }
                }
            }

            // Simpan file gabungan
            $xlsxFileName = 'exports/appraisal_details_' . $this->userId . '.xlsx';
            $outputPath = storage_path('app/public/' . $xlsxFileName);

            try {
                // Overwrite the file if it exists
                if (file_exists($outputPath)) {
                    unlink($outputPath); // Delete the existing file
                    Log::info($this->userId . ': Existing file deleted: ' . $outputPath);
                }

                // Save the new XLSX file
                $writer = new Xlsx($spreadsheet);
                $writer->save($outputPath);
                Log::info($this->userId . ': XLSX file saved successfully: ' . $outputPath);
            } catch (\Exception $e) {
                Log::error($this->userId . ': Failed to save XLSX file: ' . $e->getMessage());
                throw new \Exception('Failed to save XLSX file');
            }

        } catch (\Exception $e) {
            Log::error("ExportAppraisalDetails failed: " . $e->getMessage());
            throw $e; // Rethrow to mark the job as failed
        }
            
    }

    public function tags()
    {
        // Optional: Add tags for easier tracking (visible in Horizon if used)
        return ['user:' . $this->userId, 'job:' . $this->jobTrackingId];
    }

    public $tries = 3;
}