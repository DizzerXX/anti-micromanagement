<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Reports\OverdueItem;
use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportData;
use App\Enums\PeriodType;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Report;
use App\Models\Task;
use App\Models\TaskEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Data for the Today page (HANDOFF §2): this week's report plus a few task
 * queries. No numbers are computed here that the report engine owns.
 */
class TodayOverview
{
    public const NEEDS_UPDATE_LIMIT = 3;

    public const DUE_LIMIT = 4;

    public function __construct(private readonly ReportBuilder $reports) {}

    public function hasProjects(): bool
    {
        return Project::query()->active()->exists();
    }

    public function week(): ReportData
    {
        return $this->reports->build($this->reports->period(PeriodType::Weekly, CarbonImmutable::today()));
    }

    /**
     * Today's updates, newest first.
     *
     * @return Collection<int, TaskEvent>
     */
    public function loggedToday(): Collection
    {
        return TaskEvent::query()
            ->whereNotNull('from_status')
            ->where('logged_for', CarbonImmutable::today()->toDateString())
            ->whereHas('task.project', fn (Builder $q) => $q->whereNull('archived_at'))
            ->with(['task.project', 'task.module'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Overdue first (most late first), then the nearest due dates.
     *
     * @return array{rows: list<array{task: Task, overdue: ?int}>, more: bool}
     */
    public function dueAndOverdue(ReportData $week): array
    {
        $overdueIds = array_map(fn (OverdueItem $item): int => $item->task->id, $week->overdue);
        $overdueDays = array_combine($overdueIds, array_map(fn (OverdueItem $item): int => $item->daysLate, $week->overdue));

        $overdue = Task::query()->whereIn('id', $overdueIds)->with(['project', 'module'])->get()
            ->sortBy(fn (Task $task): int => array_search($task->id, $overdueIds, true))
            ->values();

        $upcomingQuery = Task::query()
            ->whereHas('project', fn (Builder $q) => $q->whereNull('archived_at'))
            ->where('current_status', '!=', TaskStatus::Done->value)
            ->whereNotNull('due_date')
            ->where('due_date', '>=', CarbonImmutable::today()->toDateString());

        $upcoming = (clone $upcomingQuery)
            ->with(['project', 'module'])
            ->orderBy('due_date')
            ->orderBy('title')
            ->limit(self::DUE_LIMIT)
            ->get();

        $rows = $overdue->map(fn (Task $task): array => ['task' => $task, 'overdue' => $overdueDays[$task->id]])
            ->concat($upcoming->map(fn (Task $task): array => ['task' => $task, 'overdue' => null]));

        return [
            'rows' => $rows->take(self::DUE_LIMIT)->values()->all(),
            'more' => $overdue->count() + $upcomingQuery->count() > self::DUE_LIMIT,
        ];
    }

    public function lastReport(): ?Report
    {
        return Report::query()->latest('created_at')->latest('id')->first();
    }
}
