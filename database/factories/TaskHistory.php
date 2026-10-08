<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Creates a task with a realistic history by running every step through
 * TaskStatusService, with the clock moved to each step's moment so
 * `created_at` and the future-date guard behave as they would live.
 *
 *     TaskHistory::make($project, 'Weekly diff query', [
 *         ['in_progress', '2026-09-25'],
 *         ['done', '2026-10-08 10:42', 'Handles the boundary.'],
 *     ], module: $module, createdOn: '2026-09-24');
 *
 * A step is [status, logged_for (date or "date HH:MM"), ?note]. The status
 * may be a TaskStatus or its value; the same status as before is a progress note.
 */
final class TaskHistory
{
    /**
     * @param  list<array{0: TaskStatus|string, 1: string, 2?: ?string}>  $steps
     */
    public static function make(
        Project $project,
        string $title,
        array $steps = [],
        ?Module $module = null,
        ?string $createdOn = null,
        ?string $due = null,
    ): Task {
        $service = app(TaskStatusService::class);
        $realNow = Carbon::getTestNow();

        $firstStep = $steps[0][1] ?? null;
        $created = self::moment($createdOn ?? ($firstStep !== null ? substr($firstStep, 0, 10) : CarbonImmutable::today()->toDateString()), '08:00');

        try {
            Carbon::setTestNow($created);
            $task = $service->create($project, $title, $module, $due ? CarbonImmutable::parse($due) : null, $created);

            foreach ($steps as $step) {
                $at = self::moment($step[1], '17:00');
                Carbon::setTestNow($at);

                $status = $step[0] instanceof TaskStatus ? $step[0] : TaskStatus::from($step[0]);
                $service->log($task, $status, $step[2] ?? null, $at->startOfDay());
            }
        } finally {
            Carbon::setTestNow($realNow);
        }

        $service->refreshCaches($task);

        return $task->refresh();
    }

    private static function moment(string $value, string $defaultTime): CarbonImmutable
    {
        return strlen($value) > 10
            ? CarbonImmutable::parse($value)
            : CarbonImmutable::parse("{$value} {$defaultTime}");
    }
}
