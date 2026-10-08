<?php

declare(strict_types=1);

namespace App\Filament\GlobalSearch;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\GlobalSearch\GlobalSearchResult;
use Filament\GlobalSearch\GlobalSearchResults;
use Filament\GlobalSearch\Providers\Contracts\GlobalSearchProvider;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Search tasks and projects" in the top bar.
 */
class LedgerSearchProvider implements GlobalSearchProvider
{
    private const LIMIT = 8;

    public function getResults(string $query): ?GlobalSearchResults
    {
        $query = trim($query);

        if ($query === '') {
            return null;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $query).'%';

        $tasks = Task::query()
            ->whereHas('project', fn (Builder $q) => $q->whereNull('archived_at'))
            ->where('title', 'like', $like)
            ->with(['project', 'module'])
            ->orderByDesc('last_logged_for')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Task $task): GlobalSearchResult => new GlobalSearchResult(
                title: $task->title,
                url: ProjectResource::getUrl('view', ['record' => $task->project_id]),
                details: ['Path' => $task->path(), 'Status' => $task->current_status->label()],
                actions: [
                    Action::make('log')
                        ->label('Log update')
                        ->alpineClickHandler("\$dispatch('open-log-update', { taskId: {$task->id} })"),
                ],
            ));

        $projects = Project::query()
            ->active()
            ->where('name', 'like', $like)
            ->withCount('openTasks')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Project $project): GlobalSearchResult => new GlobalSearchResult(
                title: $project->name,
                url: ProjectResource::getUrl('view', ['record' => $project]),
                details: ['Open' => (string) $project->open_tasks_count],
            ));

        $results = GlobalSearchResults::make();

        if ($tasks->isNotEmpty()) {
            $results->category('Tasks', $tasks);
        }

        if ($projects->isNotEmpty()) {
            $results->category('Projects', $projects);
        }

        return $results;
    }
}
