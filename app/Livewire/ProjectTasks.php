<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\InvalidTaskUpdate;
use App\Enums\TaskStatus;
use App\Filament\Resources\Projects\TaskForm;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Services\Settings;
use App\Services\TaskDetailsService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Tasks tab of the project page (HANDOFF §4): grouped by module with
 * collapsible groups; fully done groups start collapsed.
 */
class ProjectTasks extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Locked]
    public int $projectId;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    #[Url(as: 'module')]
    public string $moduleFilter = '';

    #[Url(as: 'group')]
    public bool $groupByModule = true;

    /** @var array<string, bool> group key => collapsed */
    public array $collapsed = [];

    public function mount(int $projectId): void
    {
        $this->projectId = $projectId;

        foreach ($this->groups($this->tasks()) as $group) {
            $this->collapsed[$group['key']] = $group['total'] > 0 && $group['done'] === $group['total'];
        }
    }

    #[On('task-logged')]
    #[On('tasks-changed')]
    public function refreshTasks(): void
    {
        // Re-render.
    }

    public function toggleGroup(string $key): void
    {
        $this->collapsed[$key] = ! ($this->collapsed[$key] ?? false);
    }

    public function editTaskAction(): Action
    {
        return Action::make('editTask')
            ->label('Edit')
            ->modalHeading('Edit task')
            ->modalSubmitActionLabel('Save')
            ->modalWidth('lg')
            ->schema(fn (): array => TaskForm::fields($this->project()))
            ->fillForm(function (array $arguments): array {
                $task = $this->findTask($arguments);

                return [
                    'title' => $task->title,
                    'module_id' => $task->module_id,
                    'due_date' => $task->due_date?->toDateString(),
                ];
            })
            ->action(function (array $data, array $arguments): void {
                $task = $this->findTask($arguments);

                $this->guard(fn () => app(TaskDetailsService::class)->update(
                    $task,
                    (string) $data['title'],
                    $data['module_id'] ? Module::query()->find($data['module_id']) : null,
                    $data['due_date'] ? CarbonImmutable::parse($data['due_date']) : null,
                ));

                Notification::make()->title('Task saved.')->body($task->title)->success()->send();
                $this->dispatch('tasks-changed');
            });
    }

    public function moveTaskAction(): Action
    {
        return Action::make('moveTask')
            ->label('Move to module')
            ->modalHeading(fn (array $arguments): string => 'Move '.$this->findTask($arguments)->title)
            ->modalSubmitActionLabel('Move')
            ->modalWidth('md')
            ->schema(fn (): array => [
                Select::make('module_id')
                    ->label('Module')
                    ->placeholder('No module')
                    ->options(fn (): array => $this->project()->modules()->pluck('name', 'id')->all()),
            ])
            ->fillForm(fn (array $arguments): array => ['module_id' => $this->findTask($arguments)->module_id])
            ->action(function (array $data, array $arguments): void {
                $task = $this->findTask($arguments);
                $module = $data['module_id'] ? Module::query()->find($data['module_id']) : null;

                $this->guard(fn () => app(TaskDetailsService::class)->move($task, $module));

                Notification::make()->title('Moved.')->body($task->title.' is now in '.($module->name ?? 'No module').'.')->success()->send();
                $this->dispatch('tasks-changed');
            });
    }

    public function deleteTaskAction(): Action
    {
        return Action::make('deleteTask')
            ->label('Delete')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => 'Delete '.$this->findTask($arguments)->title.'?')
            ->modalDescription(function (array $arguments): string {
                $updates = $this->findTask($arguments)->events()->whereNotNull('from_status')->count();

                return 'It disappears from this project and from new reports, with its '.$updates.' '.($updates === 1 ? 'update' : 'updates').'. The history is kept, and report files already generated are not changed.';
            })
            ->modalSubmitActionLabel('Delete task')
            ->action(function (array $arguments): void {
                $task = $this->findTask($arguments);
                app(TaskDetailsService::class)->delete($task);

                Notification::make()->title('Deleted.')->body($task->title)->success()->send();
                $this->dispatch('tasks-changed');
                $this->dispatch('refresh-sidebar');
            });
    }

    public function render(): View
    {
        $all = $this->tasks();
        $filtered = $this->applyFilters($all);
        $filtering = $this->search !== '' || $this->statusFilter !== '' || $this->moduleFilter !== '';

        $groups = $this->groupByModule
            ? array_values(array_filter(
                $this->groups($filtered),
                fn (array $group): bool => ! $filtering || $group['tasks']->isNotEmpty(),
            ))
            : [];

        return view('livewire.project-tasks', [
            'project' => $this->project(),
            'modules' => $this->project()->modules,
            'statuses' => TaskStatus::cases(),
            'groups' => $groups,
            'flat' => $filtered,
            'total' => $all->count(),
            'filtering' => $filtering,
            'today' => CarbonImmutable::today(),
            'staleDays' => app(Settings::class)->staleDays(),
        ]);
    }

    private function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }

    /**
     * @return Collection<int, Task>
     */
    private function tasks(): Collection
    {
        return Task::query()
            ->where('project_id', $this->projectId)
            ->with(['module', 'latestUpdate', 'latestBlock'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    private function applyFilters(Collection $tasks): Collection
    {
        $term = mb_strtolower(trim($this->search));

        return $tasks
            ->filter(fn (Task $task): bool => $term === '' || str_contains(mb_strtolower($task->title), $term))
            ->filter(fn (Task $task): bool => $this->statusFilter === '' || $task->current_status->value === $this->statusFilter)
            ->filter(fn (Task $task): bool => match ($this->moduleFilter) {
                '' => true,
                'none' => $task->module_id === null,
                default => (string) $task->module_id === $this->moduleFilter,
            })
            ->values();
    }

    /**
     * Modules in position order, then "No module" when it has tasks.
     *
     * @param  Collection<int, Task>  $tasks
     * @return list<array{key: string, name: string, module: ?Module, tasks: Collection<int, Task>, done: int, total: int}>
     */
    private function groups(Collection $tasks): array
    {
        $groups = [];

        foreach ($this->project()->modules as $module) {
            $groupTasks = $tasks->where('module_id', $module->id)->values();
            $groups[] = $this->group('m'.$module->id, $module->name, $module, $groupTasks);
        }

        $loose = $tasks->whereNull('module_id')->values();
        if ($loose->isNotEmpty()) {
            $groups[] = $this->group('none', 'No module', null, $loose);
        }

        return $groups;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array{key: string, name: string, module: ?Module, tasks: Collection<int, Task>, done: int, total: int}
     */
    private function group(string $key, string $name, ?Module $module, Collection $tasks): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'module' => $module,
            'tasks' => $tasks,
            'done' => $tasks->filter(fn (Task $t): bool => $t->current_status === TaskStatus::Done)->count(),
            'total' => $tasks->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function findTask(array $arguments): Task
    {
        return Task::query()->where('project_id', $this->projectId)->findOrFail((int) ($arguments['task'] ?? 0));
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (InvalidTaskUpdate $e) {
            throw ValidationException::withMessages(["mountedActions.0.data.{$e->field}" => $e->getMessage()]);
        }
    }
}
