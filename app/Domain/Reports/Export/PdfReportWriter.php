<?php

declare(strict_types=1);

namespace App\Domain\Reports\Export;

use App\Domain\Reports\ReportData;
use Barryvdh\DomPDF\PDF;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Storage;

/**
 * A4 portrait PDF of ReportData (SPEC §6, mockups/ReportPdf.html).
 */
class PdfReportWriter
{
    /** Matches @page side margin in resources/pdf/report.css. */
    private const SIDE_MARGIN = 54.0;

    /** Top of the footer text, measured from the bottom of the page. */
    private const FOOTER_TEXT_FROM_BOTTOM = 44.5;

    /** text-secondary (#57554E) as PDF RGB. */
    private const SECONDARY_RGB = [0.341, 0.333, 0.306];

    public function __construct(private readonly PDF $pdf) {}

    /**
     * Writes the PDF to the reports disk at $path.
     */
    public function write(ReportData $data, string $path): void
    {
        if ((bool) config('ledger.force_failure.pdf')) {
            throw new ReportFileFailed('Forced failure: LEDGER_FORCE_PDF_FAILURE is true.');
        }

        $bytes = $this->render($data, basename($path));

        Storage::disk('reports')->put($path, $bytes);
    }

    public function render(ReportData $data, string $filename): string
    {
        $css = str_replace(
            '{{fonts}}',
            resource_path('fonts'),
            (string) file_get_contents(resource_path('pdf/report.css')),
        );

        $dompdf = $this->pdf
            ->loadView('reports.pdf', ['data' => $data, 'filename' => $filename, 'css' => $css])
            ->setPaper('a4', 'portrait')
            ->getDomPDF();

        $dompdf->render();
        $this->pageNumbers($dompdf);

        return (string) $dompdf->output();
    }

    /**
     * "Page n of N" in the footer, right-aligned (CSS page counters are not
     * reliable in dompdf).
     */
    private function pageNumbers(Dompdf $dompdf): void
    {
        $canvas = $dompdf->getCanvas();
        $metrics = $dompdf->getFontMetrics();
        $font = $metrics->getFont('IBM Plex Mono') ?? $metrics->getFont('monospace');
        $size = 9.0;
        $sample = 'Page 9 of 9';
        $right = $canvas->get_width() - self::SIDE_MARGIN;

        $canvas->page_text(
            $right - $metrics->getTextWidth($sample, $font, $size),
            $canvas->get_height() - self::FOOTER_TEXT_FROM_BOTTOM,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            $size,
            self::SECONDARY_RGB,
        );
    }
}
