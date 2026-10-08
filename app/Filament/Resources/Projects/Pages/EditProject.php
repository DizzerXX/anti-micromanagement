<?php

declare(strict_types=1);

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\Actions\ArchiveProjectAction;
use App\Filament\Resources\Projects\ProjectResource;
use Filament\Resources\Pages\EditRecord;

class EditProject extends EditRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ArchiveProjectAction::make()->color('gray'),
        ];
    }

    public function getTitle(): string
    {
        return 'Edit project';
    }

    protected function getRedirectUrl(): string
    {
        return ProjectResource::getUrl('view', ['record' => $this->getRecord()]);
    }

    protected function afterSave(): void
    {
        $this->dispatch('refresh-sidebar');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Project saved.';
    }
}
