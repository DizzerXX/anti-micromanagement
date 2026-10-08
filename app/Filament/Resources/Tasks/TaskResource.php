<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Models\Task;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

/**
 * A flat list of all tasks, reached from "Show all" under Due and overdue.
 * Tasks are created and edited from their project page.
 */
class TaskResource extends Resource
{
    protected static ?string $model = Task::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isGloballySearchable = false;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('project', fn (Builder $q) => $q->whereNull('archived_at'))
            ->with(['project', 'module', 'latestUpdate']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
        ];
    }
}
