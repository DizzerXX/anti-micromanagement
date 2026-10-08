<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\InvalidTaskUpdate;
use App\Domain\StatusTimeline;
use App\Enums\TaskStatus;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of task status (SPEC §4). Every change is a new
 * task_events row, written in the same transaction as the task caches.
 */
class TaskStatusService
{
    public const NOTE_MAX = 500;

    public function create(
        Project $project,
        string $title,
        ?Module $module = null,
        ?CarbonInterface $dueDate = null,
        ?CarbonInterface $loggedFor = null,
    ): Task {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > 200) {
            throw new InvalidTaskUpdate('Enter a title of 1 to 200 characters.', 'title');
        }

        if ($module !== null && $module->project_id !== $project->id) {
            throw new InvalidTaskUpdate('Pick a module from this project.', 'module_id');
        }

        $loggedFor = $this->guardDate($loggedFor);

        return DB::transaction(function () use ($project, $title, $module, $dueDate, $loggedFor): Task {
            $task = new Task([
                'project_id' => $project->id,
                'module_id' => $module?->id,
                'title' => $title,
                'due_date' => $dueDate?->toDateString(),
            ]);
            $task->current_status = TaskStatus::Todo;
            $task->last_logged_for = $loggedFor;
            $task->save();

            TaskEvent::query()->create([
                'task_id' => $task->id,
                'from_status' => null,
                'to_status' => TaskStatus::Todo,
                'note' => null,
                'logged_for' => $loggedFor->toDateString(),
                'created_at' => CarbonImmutable::now(),
            ]);

            return $task;
        });
    }

    /**
     * Log a status change, or a progress note when $to equals the status
     * the task had on $loggedFor.
     */
    public function log(Task $task, TaskStatus $to, ?string $note = null, ?CarbonInterface $loggedFor = null): TaskEvent
    {
        $note = $this->normaliseNote($note);
        $loggedFor = $this->guardDate($loggedFor);

        return DB::transaction(function () use ($task, $to, $note, $loggedFor): TaskEvent {
            /** @var Task $locked */
            $locked = Task::query()->lockForUpdate()->findOrFail($task->id);
            $timeline = StatusTimeline::forTask($locked);

            $from = $timeline->statusAt($loggedFor);

            if ($from === null) {
                throw InvalidTaskUpdate::beforeCreation($timeline->createdOn()?->format('j M Y') ?? 'the creation date');
            }

            if (! $from->canTransitionTo($to)) {
                throw InvalidTaskUpdate::transition($from, $to);
            }

            if ($to->requiresNoteFrom($from) && $note === null) {
                throw InvalidTaskUpdate::noteRequired($from, $to);
            }

            $event = TaskEvent::query()->create([
                'task_id' => $locked->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'logged_for' => $loggedFor->toDateString(),
                'created_at' => CarbonImmutable::now(),
            ]);

            $this->refreshCaches($locked);
            $task->setRawAttributes($locked->getAttributes(), true);

            return $event;
        });
    }

    public function transition(Task $task, TaskStatus $to, ?string $note = null, ?CarbonInterface $loggedFor = null): TaskEvent
    {
        return $this->log($task, $to, $note, $loggedFor);
    }

    public function progressNote(Task $task, string $note, ?CarbonInterface $loggedFor = null): TaskEvent
    {
        $status = StatusTimeline::forTask($task)->statusAt($this->guardDate($loggedFor));

        return $this->log($task, $status ?? $task->current_status, $note, $loggedFor);
    }

    /**
     * Delete the task's latest event if it was created under 60 seconds ago
     * (SPEC §4.4). The only delete of an event in the system.
     */
    public function undo(TaskEvent $event): Task
    {
        return DB::transaction(function () use ($event): Task {
            /** @var Task $task */
            $task = Task::query()->lockForUpdate()->findOrFail($event->task_id);

            $latestId = TaskEvent::query()->where('task_id', $task->id)->max('id');
            $age = $event->created_at->diffInSeconds(CarbonImmutable::now(), true);

            if ($event->isCreation()
                || (int) $latestId !== $event->id
                || $age >= (int) config('ledger.undo_seconds')) {
                throw InvalidTaskUpdate::undoRefused();
            }

            TaskEvent::query()->whereKey($event->id)->delete();
            $this->refreshCaches($task);

            return $task;
        });
    }

    /**
     * Recompute `current_status` and `last_logged_for` by replay (SPEC §4.5).
     */
    public function refreshCaches(Task $task): void
    {
        $timeline = StatusTimeline::forTask($task);

        $task->current_status = $timeline->statusAt(CarbonImmutable::today()) ?? TaskStatus::Todo;
        $task->last_logged_for = $timeline->lastLoggedFor() ?? CarbonImmutable::today();
        $task->save();
    }

    private function guardDate(?CarbonInterface $loggedFor): CarbonImmutable
    {
        $date = CarbonImmutable::parse($loggedFor ?? CarbonImmutable::today())->startOfDay();

        if ($date->gt(CarbonImmutable::today())) {
            throw InvalidTaskUpdate::futureDate();
        }

        return $date;
    }

    private function normaliseNote(?string $note): ?string
    {
        $note = $note === null ? null : trim($note);

        if ($note === null || $note === '') {
            return null;
        }

        if (mb_strlen($note) > self::NOTE_MAX) {
            throw InvalidTaskUpdate::noteTooLong();
        }

        return $note;
    }
}
