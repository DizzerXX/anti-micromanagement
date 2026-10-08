<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\InvalidTaskUpdate;
use App\Domain\StatusTimeline;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Services\TaskStatusService;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The global "Log update" slide-over (HANDOFF §3, mockups/LogUpdate.html).
 * Opened with `L` anywhere or from a row with the task pre-filled. All rules
 * live in TaskStatusService; this component only collects input.
 */
class LogUpdate extends Component
{
    private const OPTIONS_LIMIT = 8;

    public bool $isOpen = false;

    public ?int $taskId = null;

    public string $taskSearch = '';

    public ?string $status = null;

    public string $note = '';

    public string $loggedFor = '';

    #[On('open-log-update')]
    public function open(?int $taskId = null): void
    {
        $this->resetErrorBag();
        $this->reset('taskId', 'taskSearch', 'status', 'note');
        $this->loggedFor = CarbonImmutable::today()->toDateString();

        if ($taskId !== null && Task::query()->whereKey($taskId)->exists()) {
            $this->selectTask($taskId);
        }

        $this->isOpen = true;
        $this->dispatch('log-update-opened', focus: $this->taskId === null ? 'task' : 'status');
    }

    public function close(): void
    {
        $this->isOpen = false;
    }

    public function selectTask(int $taskId): void
    {
        $this->taskId = $taskId;
        unset($this->task);
        $this->taskSearch = $this->task?->title ?? '';
        $this->status = $this->statusOnDate()?->value;
        $this->resetErrorBag(['taskId', 'status']);
    }

    public function clearTask(): void
    {
        $this->reset('taskId', 'status');
    }

    public function updatedStatus(): void
    {
        $this->checkTransition();
    }

    public function updatedLoggedFor(): void
    {
        $this->resetErrorBag('loggedFor');
        $this->checkTransition();
    }

    #[Computed]
    public function task(): ?Task
    {
        return $this->taskId === null ? null : Task::query()->with(['project', 'module'])->find($this->taskId);
    }

    /**
     * Status the task had on the chosen date: what "from" will be.
     */
    #[Computed]
    public function currentStatus(): ?TaskStatus
    {
        return $this->statusOnDate();
    }

    /**
     * Recent tasks first; typing searches all tasks (title, project, module).
     *
     * @return Collection<int, Task>
     */
    #[Computed]
    public function options(): Collection
    {
        $term = trim($this->taskSearch);

        // The input shows the chosen task's title; that is not a search.
        if ($this->task !== null && $term === $this->task->title) {
            $term = '';
        }

        return Task::query()
            ->whereHas('project', fn (Builder $q) => $q->whereNull('archived_at'))
            ->when($term !== '', function (Builder $q) use ($term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
                $q->where(fn (Builder $w) => $w
                    ->where('title', 'like', $like)
                    ->orWhereHas('project', fn (Builder $p) => $p->where('name', 'like', $like))
                    ->orWhereHas('module', fn (Builder $m) => $m->where('name', 'like', $like)));
            })
            ->with(['project', 'module'])
            ->orderByDesc(TaskEvent::query()->select('created_at')->whereColumn('task_events.task_id', 'tasks.id')->latest('created_at')->limit(1))
            ->orderBy('title')
            ->limit(self::OPTIONS_LIMIT)
            ->get();
    }

    public function save(bool $another = false): void
    {
        $this->resetErrorBag();

        try {
            $this->validateInput();
        } catch (ValidationException $e) {
            $this->dispatch('log-update-error');

            throw $e;
        }

        $task = $this->task;

        if ($task === null) {
            $this->addError('taskId', 'That task no longer exists. Pick another.');

            return;
        }

        try {
            $event = app(TaskStatusService::class)->log(
                $task,
                TaskStatus::from((string) $this->status),
                $this->note,
                CarbonImmutable::parse($this->loggedFor),
            );
        } catch (InvalidTaskUpdate $e) {
            $this->addError($this->fieldFor($e->field), $e->getMessage());
            $this->dispatch('log-update-error');

            return;
        }

        $this->notifyLogged($task, $event);
        $this->dispatch('task-logged', taskId: $task->id);
        $this->dispatch('refresh-sidebar');

        if ($another) {
            $this->reset('taskId', 'taskSearch', 'status', 'note');
            unset($this->task, $this->currentStatus);
            $this->dispatch('log-update-opened', focus: 'task');

            return;
        }

        $this->isOpen = false;
    }

    private function validateInput(): void
    {
        $this->validate([
            'taskId' => ['required', 'integer'],
            'status' => ['required', 'in:'.implode(',', array_column(TaskStatus::cases(), 'value'))],
            'note' => ['nullable', 'string', 'max:'.TaskStatusService::NOTE_MAX],
            'loggedFor' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ], [
            'taskId.required' => 'Pick a task. Type part of its name to search.',
            'status.required' => 'Pick a status.',
            'note.max' => 'Shorten the note to '.TaskStatusService::NOTE_MAX.' characters or fewer.',
            'loggedFor.required' => 'Pick a date. Today is the default.',
            'loggedFor.date_format' => 'Pick a date from the calendar.',
            'loggedFor.before_or_equal' => 'Pick today or an earlier date. Updates can’t be logged for the future.',
        ]);
    }

    #[On('undo-log')]
    public function undo(int $eventId): void
    {
        $event = TaskEvent::query()->find($eventId);

        try {
            if ($event === null) {
                throw InvalidTaskUpdate::undoRefused();
            }

            $task = app(TaskStatusService::class)->undo($event);
        } catch (InvalidTaskUpdate $e) {
            Notification::make()->title($e->getMessage())->danger()->duration(6000)->send();

            return;
        }

        Notification::make()
            ->title('Undone.')
            ->body("{$task->title} is back to {$task->current_status->label()}.")
            ->success()
            ->duration(6000)
            ->send();

        $this->dispatch('task-logged', taskId: $task->id);
        $this->dispatch('refresh-sidebar');
    }

    public function render(): View
    {
        return view('livewire.log-update', [
            'statuses' => TaskStatus::cases(),
            'today' => CarbonImmutable::today()->toDateString(),
            'isToday' => $this->loggedFor === CarbonImmutable::today()->toDateString(),
            'loggedForLabel' => $this->loggedFor !== '' ? Dates::day(CarbonImmutable::parse($this->loggedFor)) : '',
        ]);
    }

    private function statusOnDate(): ?TaskStatus
    {
        $task = $this->taskId === null ? null : Task::query()->find($this->taskId);

        if ($task === null) {
            return null;
        }

        $date = rescue(fn () => CarbonImmutable::parse($this->loggedFor ?: 'today'), CarbonImmutable::today(), false);

        return StatusTimeline::forTask($task)->statusAt($date->min(CarbonImmutable::today()));
    }

    /**
     * Disallowed transitions stay selectable but show the error at once
     * (HANDOFF §3).
     */
    private function checkTransition(): void
    {
        $this->resetErrorBag('status');
        unset($this->currentStatus);

        $from = $this->currentStatus;
        $to = TaskStatus::tryFrom((string) $this->status);

        if ($from !== null && $to !== null && ! $from->canTransitionTo($to)) {
            $this->addError('status', InvalidTaskUpdate::transition($from, $to)->getMessage());
        }
    }

    private function fieldFor(string $domainField): string
    {
        return match ($domainField) {
            'logged_for' => 'loggedFor',
            'status' => 'status',
            'note' => 'note',
            default => 'taskId',
        };
    }

    private function notifyLogged(Task $task, TaskEvent $event): void
    {
        $what = $event->isProgressNote()
            ? "Note added to {$task->title}."
            : "{$task->title} is now {$event->to_status->label()}.";

        Notification::make()
            ->title('Logged.')
            ->body($what)
            ->success()
            ->icon('ledger-status-done')
            ->duration(6000)
            ->actions([
                Action::make('undo')
                    ->label('Undo')
                    ->button()
                    ->color('gray')
                    ->dispatch('undo-log', ['eventId' => $event->id])
                    ->close(),
            ])
            ->send();
    }
}
