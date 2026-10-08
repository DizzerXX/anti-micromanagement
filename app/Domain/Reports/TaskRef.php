<?php

declare(strict_types=1);

namespace App\Domain\Reports;

/**
 * Row types carried by ReportData hold plain values only, so the preview,
 * PDF and XLSX can render them without touching models.
 */
final readonly class TaskRef
{
    public function __construct(
        public int $id,
        public string $title,
        public string $project,
        public ?string $module,
    ) {}

    public function path(): string
    {
        return $this->module !== null ? "{$this->project} › {$this->module}" : $this->project;
    }
}
