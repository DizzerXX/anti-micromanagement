<?php

declare(strict_types=1);

namespace App\Domain\Reports\Export;

use App\Domain\Reports\ReportData;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Spreadsheet of ReportData (SPEC §6): a Summary sheet plus one sheet per
 * included section, with real date cells.
 */
class XlsxReportWriter
{
    public function write(ReportData $data, string $path): void
    {
        if ((bool) config('ledger.force_failure.xlsx')) {
            throw new ReportFileFailed('Forced failure: LEDGER_FORCE_XLSX_FAILURE is true.');
        }

        $stored = Excel::store(new ReportWorkbook($data), $path, 'reports', ExcelType::XLSX);

        if ($stored === false) {
            throw new ReportFileFailed("Could not write {$path} to the reports folder.");
        }
    }
}
