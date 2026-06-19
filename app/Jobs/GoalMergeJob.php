<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class GoalMergeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 1;
    public int $memory  = 1024; // MB allocated to this job specifically

    public function __construct(
        protected string $tmpFolder,
        protected int    $totalParts,
        protected string $exportKey,
        protected int    $requestedBy,
    ) {}

    public function handle(): void
    {        
        $spreadsheet = new Spreadsheet();
        $spreadsheet->setActiveSheetIndex(0);       // ✅ explicitly set active sheet
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Goals');

        // Validate sheet exists before proceeding
        if ($sheet === null) {
            Log::error('GoalMergeJob: active sheet is null');
            return;
        }

        $destRow   = 1;
        $firstFile = true;

        ini_set('memory_limit', '256M');

        $files = collect(Storage::disk('local')->files($this->tmpFolder))
            ->filter(fn ($f) => str_ends_with($f, '.csv'))
            ->sort()
            ->values();

        Log::debug('GoalMergeJob files', [
            'tmpFolder' => $this->tmpFolder,
            'count'     => $files->count(),
            'files'     => $files->toArray(),
        ]);

        if ($files->isEmpty()) {
            Log::error('GoalMergeJob: no CSV files found', [
                'tmpFolder' => $this->tmpFolder,
            ]);
            return;
        }

        $finalPath    = "public/exports/goal/{$this->exportKey}.xlsx";
        $finalAbsPath = Storage::disk('local')->path($finalPath);
        @mkdir(dirname($finalAbsPath), 0755, true);

        // ✅ Write XLSX manually as a ZIP — no PhpSpreadsheet object in memory
        $this->writeXlsxFromCsvFiles($files->toArray(), $finalAbsPath);

        // Cleanup
        Storage::disk('local')->deleteDirectory($this->tmpFolder);

        // foreach ($files as $file) {
        //     $localPath = Storage::disk('local')->path($file);
        //     $isCsv     = str_ends_with($file, '.csv');

        //     if ($isCsv) {
        //         $handle    = fopen($localPath, 'r');
        //         $lineIndex = 0;

        //         while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
        //             if ($lineIndex === 0 && ! $firstFile) { $lineIndex++; continue; }
        //             if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) { $lineIndex++; continue; }

        //             $col = 1;
        //             foreach ($row as $value) {
        //                 $sheet->getCellByColumnAndRow($col, $destRow)
        //                     ->setValue(mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8'));
        //                 $col++;
        //             }
        //             $destRow++;
        //             $lineIndex++;
        //         }

        //         fclose($handle);

        //     } else {
        //         // XLSX fallback — read-only row iterator
        //         $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($localPath);
        //         $reader->setReadDataOnly(true);
        //         $reader->setReadEmptyCells(false);
        //         $wb  = $reader->load($localPath);
        //         $src = $wb->getActiveSheet();

        //         $startRow = $firstFile ? 1 : 2;
        //         foreach ($src->getRowIterator($startRow) as $row) {
        //             $cellIterator = $row->getCellIterator();
        //             $cellIterator->setIterateOnlyExistingCells(true);
        //             $rowData = [];
        //             foreach ($cellIterator as $cell) {
        //                 $rowData[$cell->getColumn()] = $cell->getValue();
        //             }
        //             if (empty(array_filter($rowData))) continue;

        //             $col = 1;
        //             foreach ($rowData as $value) {
        //                 $sheet->getCellByColumnAndRow($col, $destRow)
        //                     ->setValue(mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8'));
        //                 $col++;
        //             }
        //             $destRow++;
        //         }

        //         $wb->disconnectWorksheets();
        //         unset($wb, $src, $reader);
        //         gc_collect_cycles();
        //     }

        //     $firstFile = false;
        // }

        Log::debug('GoalMergeJob: total rows written', ['destRow' => $destRow]);

        if ($destRow <= 1) {
            Log::error('GoalMergeJob: no rows written — check CSV content');
            return;
        }

        $lastRow = $destRow - 1;

        $this->applyStyles($sheet, $lastRow);

        $finalPath    = "public/exports/goal/{$this->exportKey}.xlsx";
        $finalAbsPath = Storage::disk('local')->path($finalPath);

        @mkdir(dirname($finalAbsPath), 0755, true);

        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $writer->save($finalAbsPath);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);
        gc_collect_cycles();

        Storage::disk('local')->deleteDirectory($this->tmpFolder);

    }

    private function writeXlsxFromCsvFiles(array $files, string $outputPath): void
    {
        $tmpXml    = $outputPath . '.sheet.tmp';
        $xmlHandle = fopen($tmpXml, 'w');

        fwrite($xmlHandle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' . "\n");
        fwrite($xmlHandle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">');
        fwrite($xmlHandle, '<sheetData>');

        $rowNum    = 1;
        $firstFile = true;

        foreach ($files as $file) {
            $localPath = Storage::disk('local')->path($file);
            $handle    = fopen($localPath, 'r');
            if (! $handle) continue;

            $lineIndex = 0;

            while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
                if ($lineIndex === 0 && ! $firstFile) {
                    $lineIndex++;
                    continue;
                }

                if (empty(array_filter($row, fn ($v) => $v !== null && $v !== ''))) {
                    $lineIndex++;
                    continue;
                }

                // ✅ Only row 1 gets the header style (s="1"), all others get s="0"
                $isHeader  = ($rowNum === 1);
                $rowStyle  = $isHeader ? ' customFormat="1"' : '';

                fwrite($xmlHandle, '<row r="' . $rowNum . '"' . $rowStyle . '>');

                foreach ($row as $colIndex => $value) {
                    $colLetter = $this->columnLetter($colIndex + 1);
                    $cellRef   = $colLetter . $rowNum;
                    $escaped   = htmlspecialchars(
                        mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8'),
                        ENT_XML1 | ENT_QUOTES,
                        'UTF-8'
                    );

                    // ✅ Apply style s="1" only to header row cells
                    $cellStyle = $isHeader ? ' s="1"' : '';

                    fwrite($xmlHandle, '<c r="' . $cellRef . '"' . $cellStyle . ' t="inlineStr">'
                        . '<is><t>' . $escaped . '</t></is>'
                        . '</c>');
                }

                fwrite($xmlHandle, '</row>');
                $rowNum++;
                $lineIndex++;
            }

            fclose($handle);
            $firstFile = false;
            unset($handle);
        }

        $lastRow = $rowNum - 1;

        fwrite($xmlHandle, '</sheetData>');
        fwrite($xmlHandle, '</worksheet>');
        fclose($xmlHandle);

        // ✅ No second pass needed — skip the styled tmp file entirely
        $zip = new \ZipArchive();
        $zip->open($outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles($lastRow));
        $zip->addFile($tmpXml, 'xl/worksheets/sheet1.xml'); // ✅ directly use tmpXml
        $zip->addFromString('docProps/app.xml', $this->appXml());
        $zip->addFromString('docProps/core.xml', $this->coreXml());

        $zip->close();
        unlink($tmpXml); // ✅ clean up only one temp file now
    }

    private function columnLetter(int $colNumber): string
    {
        $letter = '';
        while ($colNumber > 0) {
            $colNumber--;
            $letter     = chr(65 + ($colNumber % 26)) . $letter;
            $colNumber  = intdiv($colNumber, 26);
        }
        return $letter;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml"  ContentType="application/xml"/>
    <Override PartName="/xl/workbook.xml"
        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
    <Override PartName="/xl/worksheets/sheet1.xml"
        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
    <Override PartName="/xl/styles.xml"
        ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
    <Override PartName="/docProps/app.xml"
        ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
    <Override PartName="/docProps/core.xml"
        ContentType="application/package/2006/metadata/core-properties+xml"/>
    </Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument"
        Target="xl/workbook.xml"/>
    <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties"
        Target="docProps/core.xml"/>
    <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties"
        Target="docProps/app.xml"/>
    </Relationships>';
    }

    private function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"
            xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
    <sheets>
        <sheet name="Goals" sheetId="1" r:id="rId1"/>
    </sheets>
    </workbook>';
    }

    private function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
    <Relationship Id="rId1"
        Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"
        Target="worksheets/sheet1.xml"/>
    <Relationship Id="rId2"
        Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles"
        Target="styles.xml"/>
    </Relationships>';
    }

    private function styles(int $lastRow): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
    <fonts count="2">
        <font><sz val="11"/><name val="Calibri"/></font>
        <font><b/><sz val="11"/><name val="Calibri"/></font>
    </fonts>
    <fills count="3">
        <fill><patternFill patternType="none"/></fill>
        <fill><patternFill patternType="gray125"/></fill>
        <fill>
        <patternFill patternType="solid">
            <fgColor rgb="FFFFFF00"/>
        </patternFill>
        </fill>
    </fills>
    <borders count="1">
        <border><left/><right/><top/><bottom/><diagonal/></border>
    </borders>
    <cellStyleXfs count="1">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>
    </cellStyleXfs>
    <cellXfs count="2">
        <xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>
        <xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>
    </cellXfs>
    </styleSheet>';
    }

    private function appXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">
    <Application>Microsoft Excel</Application>
    </Properties>';
    }

    private function coreXml(): string
    {
        $date = now()->toIso8601String();
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
    <cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"
    xmlns:dc="http://purl.org/dc/elements/1.1/">
    <dc:creator>System</dc:creator>
    <cp:lastModifiedBy>System</cp:lastModifiedBy>
    <cp:created>' . $date . '</cp:created>
    </cp:coreProperties>';
    }

    private function applyStyles($sheet, int $lastRow): void
    {
        // ✅ Header bold + yellow
        $sheet->getStyle('A1:R1')->applyFromArray([
            'font' => ['bold' => true],
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                // 'startColor' => ['rgb' => 'FFFF00'],
            ],
        ]);

        // ✅ Use explicit widths instead of autoSize — autoSize reads every cell = slow + corrupt on large sheets
        $columnWidths = [
            'A' => 15, 'B' => 25, 'C' => 20, 'D' => 20,
            'E' => 10, 'F' => 35, 'G' => 12, 'H' => 15,
            'I' => 12, 'J' => 15, 'K' => 30, 'L' => 15,
            'M' => 15, 'N' => 25, 'O' => 20, 'P' => 25,
            'Q' => 20, 'R' => 10,
        ];

        foreach ($columnWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // ✅ Percentage format scoped to actual data range only
        if ($lastRow > 1) {
            $sheet->getStyle("I2:I{$lastRow}")
                ->getNumberFormat()
                ->setFormatCode(NumberFormat::FORMAT_PERCENTAGE);
        }

    }
}