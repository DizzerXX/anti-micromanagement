<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Enums\ReportFile;
use Illuminate\Support\HtmlString;

/**
 * Turns a raw file-building error into "what happened + how to fix it"
 * for the partial-failure state (HANDOFF §7).
 */
class FailureExplainer
{
    public function explain(ReportFile $file, ?string $error): HtmlString
    {
        $error = (string) $error;
        $renderer = $file === ReportFile::Pdf ? 'The PDF renderer' : 'The spreadsheet writer';

        $text = match (true) {
            str_contains($error, 'Allowed memory size') => e($renderer.' ran out of memory.').' Raise <code class="ld-code">memory_limit</code> to 256M, then retry.',
            str_contains($error, 'Forced failure') => e($file->label().' building is switched off for testing.').' Set <code class="ld-code">'.($file === ReportFile::Pdf ? 'LEDGER_FORCE_PDF_FAILURE' : 'LEDGER_FORCE_XLSX_FAILURE').'</code> to false in <code class="ld-code">.env</code>, then retry.',
            str_contains($error, 'Permission denied') || str_contains($error, 'Unable to write') || str_contains($error, 'failed to open stream') || str_contains($error, 'Could not write') => e('Couldn’t write to the reports folder.').' Check that <code class="ld-code">storage/app/reports</code> is writable, then retry.',
            str_contains($error, 'font') => e($renderer.' couldn’t load a font.').' Check that <code class="ld-code">storage/fonts</code> is writable, then retry.',
            default => e($renderer.' stopped with an error.').' Check the technical details below, fix the cause, then retry.',
        };

        return new HtmlString($text);
    }
}
