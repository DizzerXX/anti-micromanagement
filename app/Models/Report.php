<?php

declare(strict_types=1);

namespace App\Models;

use App\Casts\LocalDate;
use App\Enums\PeriodType;
use App\Enums\ReportFile;
use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property PeriodType $period_type
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon $effective_end_date
 * @property ?int $project_id
 * @property list<string> $sections
 * @property list<string> $files
 * @property ?string $pdf_path
 * @property ?string $xlsx_path
 * @property ReportStatus $status
 * @property ?string $error
 * @property Carbon $created_at
 * @property-read ?Project $project
 */
class Report extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'period_type', 'start_date', 'end_date', 'effective_end_date', 'project_id',
        'sections', 'files', 'pdf_path', 'xlsx_path', 'status', 'error', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'period_type' => PeriodType::class,
            'start_date' => LocalDate::class,
            'end_date' => LocalDate::class,
            'effective_end_date' => LocalDate::class,
            'sections' => 'array',
            'files' => 'array',
            'status' => ReportStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function pathFor(ReportFile $file): ?string
    {
        return match ($file) {
            ReportFile::Pdf => $this->pdf_path,
            ReportFile::Xlsx => $this->xlsx_path,
        };
    }

    public function wants(ReportFile $file): bool
    {
        return in_array($file->value, $this->files, true);
    }

    /**
     * Files that were requested but did not build.
     *
     * @return list<ReportFile>
     */
    public function failedFiles(): array
    {
        return array_values(array_filter(
            ReportFile::cases(),
            fn (ReportFile $file): bool => $this->wants($file) && $this->pathFor($file) === null,
        ));
    }

    public function isPartial(): bool
    {
        return $this->effective_end_date->lt($this->end_date);
    }
}
