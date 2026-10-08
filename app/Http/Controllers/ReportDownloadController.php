<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ReportFile;
use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportDownloadController
{
    public function __invoke(Report $report, string $file): StreamedResponse
    {
        $type = ReportFile::from($file);
        $path = $report->pathFor($type);

        abort_if($path === null || ! Storage::disk('reports')->exists($path), 404);

        return Storage::disk('reports')->download($path, basename($path));
    }
}
