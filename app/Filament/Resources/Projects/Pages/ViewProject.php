<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Domain\InvalidTaskUpdate;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Projects\TaskForm;
use App\Models\Module;
use App\Models\Project;
use App\Services\ProjectOverview;
use App\Services\TaskStatusService;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * Project page (HANDOFF §4, mockups/Project.html).
 *
 * @property Project $record
 */
class ViewProject extends Page
{
    use InteractsWithRecord;

    protected static string $resource = ProjectResource::class;

    protected string $view = 'filament.resources.projects.view-project';

    #[Url]
    public string $tab = 'tasks';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        if (! in_array($this->tab, ['tasks', 'modules', 'activity'], true)) {
            $this->tab = 'tasks';
        }
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    #[On('task-logged')]
    #[On('tasks-changed')]
    public function refreshOverview(): void
    {
        $this->record->refresh();
    }

    public function newTaskAction(): Action
    {
        return Action::make('newTask')
            ->label('New task')
            ->icon('ledger-plus')
            ->modalHeading('New task')
            ->modalDescription('Starts in To do. Log updates on it from the Tasks tab or with L.')
            ->modalSubmitActionLabel('Create task')
            ->modalWidth('lg')
            ->schema(fn (): array => TaskForm::fields($this->record))
            ->fillForm(fn (array $arguments): array => ['module_id' => $arguments['module'] ?? null])
            ->action(function (array $data): void {
                try {
                    $task = app(TaskStatusService::class)->create(
                        $this->record,
                        (string) $data['title'],
                        $data['module_id'] ? Module::query()->find($data['module_id']) : null,
                        $data['due_date'] ? CarbonImmutable::parse($data['due_date']) : null,
                    );
                } catch (InvalidTaskUpdate $e) {
                    throw ValidationException::withMessages(["mountedActions.0.data.{$e->field}" => $e->getMessage()]);
                }

                Notification::make()->title('Task created.')->body($task->title)->success()->send();
                $this->dispatch('tasks-changed');
                $this->dispatch('refresh-sidebar');
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $overview = app(ProjectOverview::class);
        $counts = $overview->statusCounts($this->record);

        return [
            'project' => $this->record,
            'counts' => $counts,
            'moduleCount' => $this->record->modules()->count(),
            'completedThisWeek' => $overview->completedThisWeek($this->record),
            'lastUpdate' => $overview->lastUpdate($this->record),
            'today' => CarbonImmutable::today(),
        ];
    }
}
