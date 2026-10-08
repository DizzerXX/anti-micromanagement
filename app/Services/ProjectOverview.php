<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Reports\ReportBuilder;
use App\Enums\PeriodType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\TaskEvent;
use Carbon\CarbonImmutable;

/**
 * Header numbers for a project page (HANDOFF §4).
 */
class ProjectOverview
{
    public function __construct(private readonly ReportBuilder $reports) {}

    /**
     * @return array<string, int> keyed by TaskStatus value, plus 'total'
     */
    public function statusCounts(Project $project): array
    {
        $counts = $project->tasks()
            ->selectRaw('current_status, count(*) as aggregate')
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status')
            ->all();

        $result = [];
        foreach (TaskStatus::cases() as $status) {
            $result[$status->value] = (int) ($counts[$status->value] ?? 0);
        }
        $result['total'] = array_sum($result);

        return $result;
    }

    public function completedThisWeek(Project $project): int
    {
        if ($project->isArchived()) {
            return 0;
        }

        $period = $this->reports->period(PeriodType::Weekly, CarbonImmutable::today());

        return $this->reports->build($period, $project)->metric('completed')->value;
    }

    public function lastUpdate(Project $project): ?TaskEvent
    {
        return TaskEvent::query()
            ->whereNotNull('from_status')
            ->whereHas('task', fn ($q) => $q->where('project_id', $project->id))
            ->orderByDesc('logged_for')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();
    }
}
