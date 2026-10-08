<?php

declare(strict_types=1);

namespace App\Domain\Reports;

use App\Domain\StatusTimeline;
use App\Enums\PeriodType;
use App\Enums\ReportSection;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Services\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds ReportData from task events (SPEC §5). Every number in a report
 * comes from here; nothing is stored or typed.
 */
class ReportBuilder
{
    public function __construct(
        private readonly Settings $settings,
        private readonly SummarySentence $summary,
    ) {}

    public function period(PeriodType $type, CarbonInterface $date, ?CarbonInterface $today = null): Period
    {
        return Period::containing($type, $date, $this->settings->weekStart(), $today ?? CarbonImmutable::today());
    }

    /**
     * @param  list<ReportSection>|null  $sections  null = all sections
     */
    public function build(Period $period, ?Project $project = null, ?array $sections = null, ?CarbonInterface $generatedAt = null): ReportData
    {
        $staleDays = $this->settings->staleDays();
        $previous = $period->previous();
        $S = $period->start;
        $E = $period->effectiveEnd;

        $tasks = $this->tasksInScope($project);
        $projects = $this->projectsInScope($project);

        $completed = $started = $blocked = $reopened = $overdue = $stale = [];
        $counts = [];
        $prev = ['completed' => 0, 'started' => 0, 'blocked' => 0, 'overdue' => 0];
        $updates = $previousUpdates = 0;

        foreach ($projects as $p) {
            $counts[$p->id] = ['completed' => 0, 'started' => 0, 'blocked' => 0, 'open' => 0, 'prevCompleted' => 0];
        }

        foreach ($tasks as $task) {
            $timeline = new StatusTimeline($task->events);
            $ref = new TaskRef($task->id, $task->title, $task->project->name, $task->module?->name);
            $c = &$counts[$task->project_id];

            $updates += $this->countUpdates($timeline, $S, $E);
            $previousUpdates += $this->countUpdates($timeline, $previous->start, $previous->effectiveEnd);

            // Current period.
            $status = $timeline->statusAt($E);

            if ($status !== null && ($date = $this->completedOn($timeline, $S, $E)) !== null) {
                $completed[] = [new CompletedItem($ref, $date['date']), $date['order']];
                $c['completed']++;
            }

            if (($startedOn = $this->startedOn($timeline, $S, $E)) !== null) {
                $started[] = new StartedItem($ref, $startedOn);
                $c['started']++;
            }

            if ($status === TaskStatus::Blocked) {
                $block = $this->latestBlock($timeline, $E);
                $blocked[] = new BlockedItem($ref, $block?->note, $block?->logged_for->toImmutable() ?? $E, $block ? (int) $block->logged_for->diffInDays($E) : 0);
                $c['blocked']++;
            }

            if (($reopen = $this->reopenedIn($timeline, $S, $E)) !== null) {
                $reopened[] = new ReopenedItem($ref, $reopen['done'], $reopen['date']);
            }

            if ($this->isOverdue($task, $status, $E)) {
                $overdue[] = new OverdueItem($ref, $task->due_date->toImmutable(), (int) $task->due_date->diffInDays($E));
            }

            if ($status === TaskStatus::InProgress) {
                $last = $timeline->lastEventOn($E);
                $days = (int) $last->logged_for->diffInDays($E);

                if ($days >= $staleDays) {
                    $stale[] = new StaleItem($ref, $last->logged_for->toImmutable(), $days);
                }
            }

            if ($status !== null && $status !== TaskStatus::Done) {
                $c['open']++;
            }

            // Previous period, compared in full.
            $prevStatus = $timeline->statusAt($previous->effectiveEnd);

            if ($prevStatus !== null && $this->completedOn($timeline, $previous->start, $previous->effectiveEnd) !== null) {
                $prev['completed']++;
                $c['prevCompleted']++;
            }

            if ($this->startedOn($timeline, $previous->start, $previous->effectiveEnd) !== null) {
                $prev['started']++;
            }

            if ($prevStatus === TaskStatus::Blocked) {
                $prev['blocked']++;
            }

            if ($this->isOverdue($task, $prevStatus, $previous->effectiveEnd)) {
                $prev['overdue']++;
            }

            unset($c);
        }

        usort($completed, static fn (array $a, array $b): int => $b[1] <=> $a[1] ?: $a[0]->task->id <=> $b[0]->task->id);
        usort($started, static fn (StartedItem $a, StartedItem $b): int => $b->date <=> $a->date ?: $a->task->id <=> $b->task->id);
        usort($blocked, static fn (BlockedItem $a, BlockedItem $b): int => $b->days <=> $a->days ?: $a->task->id <=> $b->task->id);
        usort($reopened, static fn (ReopenedItem $a, ReopenedItem $b): int => $b->reopenedDate <=> $a->reopenedDate ?: $a->task->id <=> $b->task->id);
        usort($overdue, static fn (OverdueItem $a, OverdueItem $b): int => $b->daysLate <=> $a->daysLate ?: $a->task->id <=> $b->task->id);
        usort($stale, static fn (StaleItem $a, StaleItem $b): int => $b->days <=> $a->days ?: $a->task->id <=> $b->task->id);

        $byProject = [];
        foreach ($projects as $p) {
            $c = $counts[$p->id];
            $byProject[] = new ProjectRow($p->id, $p->name, $c['completed'], $c['started'], $c['blocked'], $c['open'], $c['completed'] - $c['prevCompleted']);
        }

        $total = new ProjectRow(
            null,
            'Total',
            array_sum(array_map(fn (ProjectRow $r): int => $r->completed, $byProject)),
            array_sum(array_map(fn (ProjectRow $r): int => $r->started, $byProject)),
            array_sum(array_map(fn (ProjectRow $r): int => $r->blocked, $byProject)),
            array_sum(array_map(fn (ProjectRow $r): int => $r->open, $byProject)),
            array_sum(array_map(fn (ProjectRow $r): int => $r->completedDelta, $byProject)),
        );

        $headline = [
            new HeadlineMetric('completed', 'Completed', count($completed), $prev['completed']),
            new HeadlineMetric('started', 'Started', count($started), $prev['started']),
            new HeadlineMetric('blocked', 'Blocked', count($blocked), $prev['blocked']),
            new HeadlineMetric('overdue', 'Overdue', count($overdue), $prev['overdue']),
        ];

        $completedItems = array_map(static fn (array $pair): CompletedItem => $pair[0], $completed);

        return new ReportData(
            period: $period,
            previous: $previous,
            projectId: $project?->id,
            projectName: $project?->name,
            staleDays: $staleDays,
            generatedAt: CarbonImmutable::parse($generatedAt ?? CarbonImmutable::now()),
            updates: $updates,
            previousUpdates: $previousUpdates,
            headline: $headline,
            summary: $this->summary->format($period->type, $headline, $blocked, count($stale), $staleDays),
            completed: $completedItems,
            started: $started,
            blocked: $blocked,
            reopened: $reopened,
            overdue: $overdue,
            stale: $stale,
            byProject: $byProject,
            total: $total,
            sections: $sections ?? ReportSection::cases(),
        );
    }

    /**
     * Number of updates (non-creation events) logged in a period, for the
     * empty state and the generating steps.
     */
    public function updatesIn(Period $period, ?Project $project = null): int
    {
        return $this->updatesQuery($project)
            ->whereBetween('logged_for', [$period->start->toDateString(), $period->effectiveEnd->toDateString()])
            ->count();
    }

    /**
     * The most recent period of the same type, before $period, that has
     * updates: [period, update count], or null when nothing was ever logged.
     *
     * @return array{0: Period, 1: int}|null
     */
    public function lastActivePeriodBefore(Period $period, ?Project $project = null, ?CarbonInterface $today = null): ?array
    {
        $latest = $this->updatesQuery($project)
            ->where('logged_for', '<', $period->start->toDateString())
            ->max('logged_for');

        if ($latest === null) {
            return null;
        }

        $active = $this->period($period->type, CarbonImmutable::parse((string) $latest), $today);

        return [$active, $this->updatesIn($active, $project)];
    }

    /**
     * @return Builder<TaskEvent>
     */
    private function updatesQuery(?Project $project): Builder
    {
        return TaskEvent::query()
            ->whereNotNull('from_status')
            ->whereHas('task', function (Builder $q) use ($project): void {
                $q->whereHas('project', fn (Builder $p) => $p->whereNull('archived_at'));
                if ($project !== null) {
                    $q->where('project_id', $project->id);
                }
            });
    }

    /**
     * @return Collection<int, Task>
     */
    private function tasksInScope(?Project $project): Collection
    {
        return Task::query()
            ->whereHas('project', fn (Builder $q) => $q->whereNull('archived_at'))
            ->when($project !== null, fn (Builder $q) => $q->where('project_id', $project?->id))
            ->with(['project', 'module', 'events'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Project>
     */
    private function projectsInScope(?Project $project): Collection
    {
        if ($project !== null) {
            return new Collection([$project]);
        }

        return Project::query()->active()->orderBy('id')->get();
    }

    private function countUpdates(StatusTimeline $timeline, CarbonInterface $start, CarbonInterface $end): int
    {
        return count(array_filter($timeline->between($start, $end), fn (TaskEvent $e): bool => ! $e->isCreation()));
    }

    /**
     * SPEC §5.2: a → done event in [S, E] and done at E. Returns the date of
     * the last → done event in the period, plus a sort key.
     *
     * @return array{date: CarbonImmutable, order: array{string, int}}|null
     */
    private function completedOn(StatusTimeline $timeline, CarbonInterface $S, CarbonInterface $E): ?array
    {
        if ($timeline->statusAt($E) !== TaskStatus::Done) {
            return null;
        }

        $last = null;
        foreach ($timeline->steps() as $step) {
            $event = $step['event'];
            if ($event->to_status === TaskStatus::Done && $step['from'] !== TaskStatus::Done
                && $event->logged_for->betweenIncluded($S, $E)) {
                $last = $event;
            }
        }

        return $last === null ? null : [
            'date' => $last->logged_for->toImmutable(),
            'order' => [$last->logged_for->toDateString(), $last->created_at->getTimestamp()],
        ];
    }

    /**
     * SPEC §5.3: the first event leaving To do goes to In progress and is
     * logged in [S, E].
     */
    private function startedOn(StatusTimeline $timeline, CarbonInterface $S, CarbonInterface $E): ?CarbonImmutable
    {
        foreach ($timeline->events() as $event) {
            if ($event->to_status === TaskStatus::Todo) {
                continue;
            }

            return $event->to_status === TaskStatus::InProgress && $event->logged_for->betweenIncluded($S, $E)
                ? $event->logged_for->toImmutable()
                : null;
        }

        return null;
    }

    private function latestBlock(StatusTimeline $timeline, CarbonInterface $E): ?TaskEvent
    {
        $block = null;
        foreach ($timeline->steps() as $step) {
            $event = $step['event'];
            if ($event->logged_for->gt($E)) {
                break;
            }
            if ($event->to_status === TaskStatus::Blocked && $step['from'] !== TaskStatus::Blocked) {
                $block = $event;
            }
        }

        return $block;
    }

    /**
     * SPEC §5.5: a done → in progress step logged in [S, E]. Uses the latest
     * reopen in the period.
     *
     * @return array{done: ?CarbonImmutable, date: CarbonImmutable}|null
     */
    private function reopenedIn(StatusTimeline $timeline, CarbonInterface $S, CarbonInterface $E): ?array
    {
        $found = null;
        $lastDone = null;

        foreach ($timeline->steps() as $step) {
            $event = $step['event'];

            if ($step['from'] === TaskStatus::Done && $event->to_status === TaskStatus::InProgress
                && $event->logged_for->betweenIncluded($S, $E)) {
                $found = ['done' => $lastDone, 'date' => $event->logged_for->toImmutable()];
            }

            if ($event->to_status === TaskStatus::Done && $step['from'] !== TaskStatus::Done) {
                $lastDone = $event->logged_for->toImmutable();
            }
        }

        return $found;
    }

    /**
     * SPEC §5.6: due strictly before E and not done at E.
     */
    private function isOverdue(Task $task, ?TaskStatus $statusAtEnd, CarbonInterface $E): bool
    {
        return $statusAtEnd !== null
            && $statusAtEnd !== TaskStatus::Done
            && $task->due_date !== null
            && $task->due_date->lt($E->copy()->startOfDay());
    }
}
