<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks\Pages;

use App\Enums\TaskStatus;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Models\Project;
use App\Models\Task;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;

class ListTasks extends ListRecords
{
    protected static string $resource = TaskResource::class;

    protected static ?string $title = 'Tasks';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    #[On('task-logged')]
    public function refreshTasks(): void {}

    public function table(Table $table): Table
    {
        $today = CarbonImmutable::today();

        return $table
            ->defaultSort(fn (Builder $query) => $query->orderByRaw('due_date is null')->orderBy('due_date')->orderBy('title'))
            ->recordUrl(fn (Task $record): string => ProjectResource::getUrl('view', ['record' => $record->project_id]))
            ->columns([
                TextColumn::make('title')
                    ->label('Task')
                    ->weight('medium')
                    ->description(fn (Task $record): string => $record->path())
                    ->searchable(),
                ViewColumn::make('current_status')
                    ->label('Status')
                    ->view('filament.columns.status-badge'),
                TextColumn::make('due_date')
                    ->label('Due')
                    ->placeholder('—')
                    ->formatStateUsing(function (Task $record) use ($today): string {
                        $late = $record->current_status !== TaskStatus::Done && $record->due_date->lt($today)
                            ? ' · '.(int) $record->due_date->diffInDays($today).'d late'
                            : '';

                        return Dates::day($record->due_date).$late;
                    })
                    ->color(fn (Task $record): ?string => $record->due_date && $record->current_status !== TaskStatus::Done && $record->due_date->lt($today) ? 'danger' : null)
                    ->extraAttributes(['class' => 'ld-col-mono'])
                    ->sortable(),
                TextColumn::make('latestUpdate.logged_for')
                    ->label('Last update')
                    ->placeholder('—')
                    ->formatStateUsing(fn (Task $record): string => $record->latestUpdate
                        ? Dates::lastUpdate($record->latestUpdate->logged_for, $record->latestUpdate->created_at, $today)
                        : '—')
                    ->extraAttributes(['class' => 'ld-col-mono']),
            ])
            ->filters([
                Filter::make('due')
                    ->label('Has a due date, not done')
                    ->toggle()
                    ->default()
                    ->query(fn (Builder $query) => $query->whereNotNull('due_date')->where('current_status', '!=', TaskStatus::Done->value)),
                SelectFilter::make('current_status')
                    ->label('Status')
                    ->options(collect(TaskStatus::cases())->mapWithKeys(fn (TaskStatus $s): array => [$s->value => $s->label()])->all()),
                SelectFilter::make('project_id')
                    ->label('Project')
                    ->options(fn (): array => Project::query()->active()->orderBy('name')->pluck('name', 'id')->all()),
            ])
            ->recordActions([
                Action::make('log')
                    ->label('Log')
                    ->button()
                    ->color('gray')
                    ->alpineClickHandler(fn (Task $record): string => "\$dispatch('open-log-update', { taskId: {$record->id} })"),
            ])
            ->emptyStateIcon('ledger-calendar')
            ->emptyStateHeading('No tasks match')
            ->emptyStateDescription('Clear a filter, or add due dates when you edit tasks on a project page.');
    }
}
