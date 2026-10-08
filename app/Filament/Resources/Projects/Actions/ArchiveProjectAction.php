<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Actions;

use App\Models\Project;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;

/**
 * Archive hides a project and leaves it out of new reports (SPEC §7);
 * restoring brings it back if no active project took its name.
 */
class ArchiveProjectAction
{
    public static function make(): Action
    {
        return Action::make('archive')
            ->label(fn (Project $record): string => $record->isArchived() ? 'Restore' : 'Archive')
            ->icon('ledger-archive')
            ->requiresConfirmation()
            ->modalHeading(fn (Project $record): string => $record->isArchived() ? "Restore {$record->name}?" : "Archive {$record->name}?")
            ->modalDescription(fn (Project $record): string => $record->isArchived()
                ? 'It shows in the sidebar and reports again.'
                : 'It disappears from lists and new reports. Reports already generated keep their files.')
            ->modalSubmitActionLabel(fn (Project $record): string => $record->isArchived() ? 'Restore' : 'Archive')
            ->action(function (Model $record, Action $action): void {
                /** @var Project $record */
                if ($record->isArchived()) {
                    $taken = Project::query()->active()->where('name', $record->name)->exists();

                    if ($taken) {
                        Notification::make()
                            ->title('Can’t restore: another active project is called '.$record->name.'. Rename one first.')
                            ->danger()
                            ->send();

                        return;
                    }
                }

                $record->update(['archived_at' => $record->isArchived() ? null : now()]);
                $action->getLivewire()->dispatch('refresh-sidebar');

                Notification::make()
                    ->title($record->isArchived() ? 'Archived.' : 'Restored.')
                    ->body($record->name)
                    ->success()
                    ->send();
            });
    }
}
