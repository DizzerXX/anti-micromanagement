<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\TaskStatus;
use App\Models\Module;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Modules tab of the project page: name, order and task counts.
 */
class ProjectModules extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithSchemas;
    use InteractsWithTable;

    #[Locked]
    public int $projectId;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Module::query()
                ->where('project_id', $this->projectId)
                ->withCount([
                    'tasks',
                    'tasks as done_count' => fn (Builder $q) => $q->where('current_status', TaskStatus::Done->value),
                ]))
            ->defaultSort('position')
            ->reorderable('position')
            ->paginated(false)
            ->columns([
                TextColumn::make('name')->label('Module')->weight('medium'),
                TextColumn::make('tasks_count')->label('Tasks')->alignEnd()->extraAttributes(['class' => 'ld-col-mono']),
                TextColumn::make('done_count')->label('Done')->alignEnd()->extraAttributes(['class' => 'ld-col-mono']),
            ])
            ->headerActions([
                $this->createAction(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Rename')
                    ->modalHeading('Rename module')
                    ->schema($this->fields())
                    ->after(fn () => $this->dispatch('tasks-changed')),
                DeleteAction::make()
                    ->color('gray')
                    ->modalHeading(fn (Module $record): string => "Delete {$record->name}?")
                    ->modalDescription('Its tasks stay in the project under No module. Their history is kept.')
                    ->modalSubmitActionLabel('Delete module')
                    ->after(fn () => $this->dispatch('tasks-changed')),
            ])
            ->emptyStateIcon('ledger-folder')
            ->emptyStateHeading('No modules yet')
            ->emptyStateDescription('Modules group tasks inside a project. They are optional.')
            ->emptyStateActions([$this->createAction()->label('New module')]);
    }

    public function render(): View
    {
        return view('livewire.project-modules');
    }

    private function createAction(): Action
    {
        return CreateAction::make()
            ->label('New module')
            ->icon('ledger-plus')
            ->color('gray')
            ->modalHeading('New module')
            ->createAnother(false)
            ->schema($this->fields())
            ->mutateDataUsing(fn (array $data): array => [
                ...$data,
                'project_id' => $this->projectId,
                'position' => (int) Module::query()->where('project_id', $this->projectId)->max('position') + 1,
            ])
            ->after(fn () => $this->dispatch('tasks-changed'));
    }

    /**
     * @return list<TextInput>
     */
    private function fields(): array
    {
        return [
            TextInput::make('name')
                ->label('Name')
                ->required()
                ->maxLength(120)
                ->unique(
                    table: Module::class,
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('project_id', $this->projectId),
                )
                ->validationMessages([
                    'required' => 'Enter a module name.',
                    'unique' => 'This project already has a module with that name.',
                ]),
        ];
    }

    public function project(): Project
    {
        return Project::query()->findOrFail($this->projectId);
    }
}
