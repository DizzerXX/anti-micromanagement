<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\TaskEvent;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Activity tab: the project's history, newest first, in the same row format
 * as "Logged today".
 */
class ProjectActivity extends Component
{
    private const PAGE = 30;

    #[Locked]
    public int $projectId;

    public int $limit = self::PAGE;

    public function showMore(): void
    {
        $this->limit += self::PAGE;
    }

    #[On('task-logged')]
    #[On('tasks-changed')]
    public function refreshActivity(): void {}

    public function render(): View
    {
        $query = TaskEvent::query()
            ->whereHas('task', fn ($q) => $q->where('project_id', $this->projectId))
            ->with(['task.project', 'task.module'])
            ->orderByDesc('logged_for')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('livewire.project-activity', [
            'events' => (clone $query)->limit($this->limit)->get(),
            'more' => $query->count() > $this->limit,
        ]);
    }
}
