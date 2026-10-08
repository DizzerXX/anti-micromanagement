<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Enums\ReportSection;
use Carbon\CarbonImmutable;

/**
 * Everything a report shows (SPEC §5). The preview, the PDF view and the XLSX
 * export all render this one object.
 */
final readonly class ReportData
{
    /**
     * @param  list<HeadlineMetric>  $headline  completed, started, blocked, overdue
     * @param  list<CompletedItem>  $completed
     * @param  list<StartedItem>  $started
     * @param  list<BlockedItem>  $blocked
     * @param  list<ReopenedItem>  $reopened
     * @param  list<OverdueItem>  $overdue
     * @param  list<StaleItem>  $stale
     * @param  list<ProjectRow>  $byProject
     * @param  list<ReportSection>  $sections
     */
    public function __construct(
        public Period $period,
        public Period $previous,
        public ?int $projectId,
        public ?string $projectName,
        public int $staleDays,
        public CarbonImmutable $generatedAt,
        public int $updates,
        public int $previousUpdates,
        public array $headline,
        public string $summary,
        public array $completed,
        public array $started,
        public array $blocked,
        public array $reopened,
        public array $overdue,
        public array $stale,
        public array $byProject,
        public ProjectRow $total,
        public array $sections,
    ) {}

    public function isEmpty(): bool
    {
        return $this->updates === 0;
    }

    public function scopeLabel(): string
    {
        return $this->projectName ?? 'All projects';
    }

    public function metric(string $key): HeadlineMetric
    {
        foreach ($this->headline as $metric) {
            if ($metric->key === $key) {
                return $metric;
            }
        }

        throw new \OutOfBoundsException("Unknown headline metric [{$key}].");
    }

    public function includes(ReportSection $section): bool
    {
        return in_array($section, $this->sections, true);
    }

    /**
     * @param  list<ReportSection>  $sections
     */
    public function withSections(array $sections): self
    {
        return new self(
            $this->period, $this->previous, $this->projectId, $this->projectName, $this->staleDays,
            $this->generatedAt, $this->updates, $this->previousUpdates, $this->headline, $this->summary,
            $this->completed, $this->started, $this->blocked, $this->reopened, $this->overdue, $this->stale,
            $this->byProject, $this->total, $sections,
        );
    }
}
