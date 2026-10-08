<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\Actions\ArchiveProjectAction;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected static ?string $title = 'Projects';

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New project')->icon('ledger-plus'),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->recordUrl(fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('Project')
                    ->weight('medium')
                    ->description(fn (Project $record): ?string => $record->description)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('modules_count')->label('Modules')->alignEnd()->extraAttributes(['class' => 'ld-col-mono']),
                TextColumn::make('tasks_count')->label('Tasks')->alignEnd()->extraAttributes(['class' => 'ld-col-mono']),
                TextColumn::make('open_tasks_count')->label('Open')->alignEnd()->extraAttributes(['class' => 'ld-col-mono']),
                TextColumn::make('is_pinned')
                    ->label('Sidebar')
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Pinned' : '—')
                    ->color('gray'),
                TextColumn::make('archived_at')
                    ->label('Archived')
                    ->date('j M Y')
                    ->placeholder('—')
                    ->extraAttributes(['class' => 'ld-col-mono'])
                    ->visible(fn (): bool => ($this->tableFilters['archived']['value'] ?? null) !== '0' && ($this->tableFilters['archived']['value'] ?? null) !== null),
            ])
            ->filters([
                TernaryFilter::make('archived')
                    ->label('Archived')
                    ->placeholder('Active only')
                    ->trueLabel('Archived only')
                    ->falseLabel('All projects')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('archived_at'),
                        false: fn (Builder $query) => $query,
                        blank: fn (Builder $query) => $query->whereNull('archived_at'),
                    ),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()->icon('ledger-pencil'),
                    Action::make('pin')
                        ->label(fn (Project $record): string => $record->is_pinned ? 'Unpin from sidebar' : 'Pin to sidebar')
                        ->icon('ledger-pin')
                        ->action(function (Project $record): void {
                            $record->update(['is_pinned' => ! $record->is_pinned]);
                            $this->dispatch('refresh-sidebar');
                        }),
                    ArchiveProjectAction::make(),
                ])->label('More actions')->icon('ledger-more')->color('gray'),
            ])
            ->emptyStateIcon('ledger-folder')
            ->emptyStateHeading('No projects yet')
            ->emptyStateDescription('Projects hold modules and tasks. Create one to start logging.')
            ->emptyStateActions([
                CreateAction::make()->label('Create project')->icon('ledger-plus'),
            ]);
    }
}
