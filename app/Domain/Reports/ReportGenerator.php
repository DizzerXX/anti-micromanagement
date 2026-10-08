<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\Reports\Export\PdfReportWriter;
use App\Domain\Reports\Export\XlsxReportWriter;
use App\Enums\ReportFile;
use App\Enums\ReportSection;
use App\Enums\ReportStatus;
use App\Models\Report;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Writes report files and records them in history (SPEC §6). If one file
 * fails, the other is kept and the report is marked partial.
 *
 * The Reports page runs the same steps one request at a time (to show
 * progress); generate() runs them all at once.
 */
class ReportGenerator
{
    public function __construct(
        private readonly ReportBuilder $builder,
        private readonly PdfReportWriter $pdf,
        private readonly XlsxReportWriter $xlsx,
    ) {}

    public function data(ReportRequest $request, ?CarbonInterface $generatedAt = null): ReportData
    {
        return $this->builder->build($request->period, $request->project, $request->sections, $generatedAt);
    }

    public function generate(ReportRequest $request, ?CarbonInterface $generatedAt = null): Report
    {
        $generatedAt = CarbonImmutable::parse($generatedAt ?? CarbonImmutable::now());
        $data = $this->data($request, $generatedAt);
        $paths = $this->reservePaths($request);
        $errors = [];

        foreach ($request->files as $file) {
            $error = $this->writeFile($file, $data, $paths[$file->value]);
            if ($error !== null) {
                $errors[$file->value] = $error;
            }
        }

        return $this->record($request, $paths, $errors, $generatedAt);
    }

    /**
     * Unique paths under {yyyy}/ for each requested file. Filenames follow
     * SPEC §6 (weekly-2026-W41.pdf, daily-2026-10-08.xlsx); a project slug is
     * added for one-project reports, and -2, -3… when the name is taken.
     *
     * @return array<string, string> file value => relative path
     */
    public function reservePaths(ReportRequest $request): array
    {
        $period = $request->period;
        $base = match ($period->type->value) {
            'weekly' => sprintf('weekly-%d-W%02d', $period->start->isoWeekYear, $period->start->isoWeek),
            default => 'daily-'.$period->start->toDateString(),
        };

        if ($request->project !== null) {
            $base .= '-'.Str::slug($request->project->name);
        }

        $dir = $period->start->format('Y');
        $disk = Storage::disk('reports');
        $suffix = '';
        $n = 1;

        while (collect(ReportFile::cases())->contains(fn (ReportFile $f): bool => $disk->exists("{$dir}/{$base}{$suffix}.{$f->value}")
            || Report::query()->where($f->value.'_path', "{$dir}/{$base}{$suffix}.{$f->value}")->exists())) {
            $suffix = '-'.(++$n);
        }

        $paths = [];
        foreach ($request->files as $file) {
            $paths[$file->value] = "{$dir}/{$base}{$suffix}.{$file->value}";
        }

        return $paths;
    }

    /**
     * Builds one file. Returns null on success, or the error message.
     */
    public function writeFile(ReportFile $file, ReportData $data, string $path): ?string
    {
        try {
            match ($file) {
                ReportFile::Pdf => $this->pdf->write($data, $path),
                ReportFile::Xlsx => $this->xlsx->write($data, $path),
            };

            return null;
        } catch (\Throwable $e) {
            Storage::disk('reports')->delete($path);
            report($e);

            return Str::limit($e->getMessage(), 2000);
        }
    }

    /**
     * @param  array<string, string>  $paths
     * @param  array<string, string>  $errors  file value => message
     */
    public function record(ReportRequest $request, array $paths, array $errors, CarbonInterface $generatedAt): Report
    {
        return Report::query()->create([
            'period_type' => $request->period->type,
            'start_date' => $request->period->start,
            'end_date' => $request->period->end,
            'effective_end_date' => $request->period->effectiveEnd,
            'project_id' => $request->project?->id,
            'sections' => array_map(fn ($s) => $s->value, $request->sections),
            'files' => array_map(fn (ReportFile $f) => $f->value, $request->files),
            'pdf_path' => isset($errors['pdf']) ? null : ($paths['pdf'] ?? null),
            'xlsx_path' => isset($errors['xlsx']) ? null : ($paths['xlsx'] ?? null),
            'status' => $this->statusFor(count($request->files), $errors),
            'error' => $errors === [] ? null : $this->formatErrors($errors),
            'created_at' => $generatedAt,
        ]);
    }

    /**
     * Rebuild a missing file from the stored options, as of the original
     * generation time.
     */
    public function retry(Report $report, ReportFile $file): Report
    {
        $request = $this->requestFor($report);
        $data = $this->data($request, $report->created_at);
        $path = $report->pathFor($file) ?? $this->retryPath($report, $file);

        $error = $this->writeFile($file, $data, $path);

        $errors = [];
        foreach ($report->failedFiles() as $failed) {
            if ($failed !== $file) {
                $errors[$failed->value] = $this->errorFor($report, $failed) ?? 'Not built.';
            }
        }
        if ($error !== null) {
            $errors[$file->value] = $error;
        }

        $report->forceFill([
            $file->value.'_path' => $error === null ? $path : null,
            'status' => $this->statusFor(count($report->files), $errors),
            'error' => $errors === [] ? null : $this->formatErrors($errors),
        ])->save();

        return $report;
    }

    public function requestFor(Report $report): ReportRequest
    {
        return new ReportRequest(
            new Period($report->period_type, $report->start_date->toImmutable(), $report->end_date->toImmutable(), $report->effective_end_date->toImmutable()),
            $report->project,
            array_values(array_filter(array_map(fn (string $s) => ReportSection::tryFrom($s), $report->sections))),
            array_values(array_filter(array_map(fn (string $f) => ReportFile::tryFrom($f), $report->files))),
        );
    }

    /**
     * The stored error for one file ("pdf: message" lines).
     */
    public function errorFor(Report $report, ReportFile $file): ?string
    {
        foreach (preg_split('/\R/', (string) $report->error) ?: [] as $line) {
            if (str_starts_with($line, $file->value.': ')) {
                return substr($line, strlen($file->value) + 2);
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function statusFor(int $requested, array $errors): ReportStatus
    {
        return match (true) {
            $errors === [] => ReportStatus::Ready,
            count($errors) >= $requested => ReportStatus::Failed,
            default => ReportStatus::Partial,
        };
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function formatErrors(array $errors): string
    {
        return collect($errors)
            ->map(fn (string $message, string $file): string => $file.': '.preg_replace('/\R+/', ' ', $message))
            ->implode("\n");
    }

    private function retryPath(Report $report, ReportFile $file): string
    {
        $sibling = collect(ReportFile::cases())->map(fn (ReportFile $f) => $report->pathFor($f))->filter()->first();

        if ($sibling !== null) {
            return preg_replace('/\.[a-z]+$/', '.'.$file->value, $sibling);
        }

        $paths = $this->reservePaths(new ReportRequest($this->requestFor($report)->period, $report->project, [], [$file]));

        return $paths[$file->value];
    }
}
