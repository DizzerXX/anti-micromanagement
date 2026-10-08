<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Enums\ReportFile;
use App\Enums\ReportSection;
use App\Models\Project;

/**
 * What the user asked for on Reports › New report.
 */
final readonly class ReportRequest
{
    /**
     * @param  list<ReportSection>  $sections
     * @param  list<ReportFile>  $files
     */
    public function __construct(
        public Period $period,
        public ?Project $project,
        public array $sections,
        public array $files,
    ) {}

    public function wants(ReportFile $file): bool
    {
        return in_array($file, $this->files, true);
    }
}
