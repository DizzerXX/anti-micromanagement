<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects;

use App\Models\Project;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

/**
 * Fields shared by New task and Edit task.
 */
final class TaskForm
{
    /**
     * @return list<Component|Field>
     */
    public static function fields(Project $project): array
    {
        return [
            TextInput::make('title')
                ->label('Title')
                ->required()
                ->maxLength(200)
                ->autofocus()
                ->validationMessages([
                    'required' => 'Enter a title.',
                    'max' => 'Keep the title to 200 characters or fewer.',
                ]),
            Select::make('module_id')
                ->label('Module')
                ->belowLabel('Optional. Tasks without one sit under No module.')
                ->placeholder('No module')
                ->options(fn (): array => $project->modules()->pluck('name', 'id')->all()),
            DatePicker::make('due_date')
                ->label('Due')
                ->belowLabel('Optional. Late tasks show under Overdue.')
                ->native(true)
                ->extraInputAttributes(['class' => 'ld-mono']),
        ];
    }
}
