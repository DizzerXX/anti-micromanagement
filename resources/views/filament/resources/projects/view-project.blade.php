@php
    use App\Enums\TaskStatus;
    use App\Support\Dates;

    $total = $counts['total'];
    $legend = [TaskStatus::Done, TaskStatus::InProgress, TaskStatus::Blocked, TaskStatus::Todo];
    $barLabel = $total.' tasks: '.collect($legend)->map(fn ($s) => $counts[$s->value].' '.mb_strtolower($s->label()))->implode(', ');
@endphp

<x-filament-panels::page>
    <div class="ld-stack-24">
        <nav aria-label="Breadcrumb" class="ld-breadcrumbs ld-pull-up">
            <a href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('index') }}">Projects</a>
            <span aria-hidden="true">/</span>
            <span aria-current="page">{{ $project->name }}</span>
        </nav>

        <div class="ld-page-head" style="margin-top: -8px">
            <div class="ld-page-titles">
                <h1 class="ld-h1">{{ $project->name }}</h1>
                <span class="ld-page-meta">
                    {{ $moduleCount }} {{ $moduleCount === 1 ? 'module' : 'modules' }}
                    · {{ $total }} {{ $total === 1 ? 'task' : 'tasks' }}
                    · @if ($lastUpdate)
                        @if ($lastUpdate->logged_for->isSameDay($today))
                            last update today at <span class="ld-mono-13">{{ $lastUpdate->created_at->format('H:i') }}</span>
                        @else
                            last update <span class="ld-mono-13">{{ Dates::day($lastUpdate->logged_for) }}</span>
                        @endif
                    @else
                        no updates yet
                    @endif
                    @if ($project->isArchived())
                        · archived
                    @endif
                </span>
            </div>
            <div class="ld-btn-row">
                <a href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('edit', ['record' => $project]) }}" class="ld-btn ld-btn-outline">Edit project</a>
                {{ $this->newTaskAction }}
            </div>
        </div>

        <section aria-labelledby="overview-h" class="ld-card ld-card-pad">
            <div class="ld-spread" style="flex-wrap: wrap">
                <h2 id="overview-h" class="ld-card-title" style="font-size: var(--text-14); line-height: var(--leading-14)">Where it stands</h2>
                <span class="ld-muted">
                    {{ $counts['done'] }} of {{ $total }} done
                    @if ($completedThisWeek > 0)
                        · <span class="ld-tone-success ld-medium">▲ {{ $completedThisWeek }} this week</span>
                    @endif
                </span>
            </div>
            <div class="ld-overview-bar" role="img" aria-label="{{ $barLabel }}">
                @if ($total === 0)
                    <span class="ld-bar-empty"></span>
                @else
                    @foreach ($legend as $status)
                        @if ($counts[$status->value] > 0)
                            <span class="ld-bar-{{ $status->color() }}" style="flex: {{ $counts[$status->value] }} 1 0"></span>
                        @endif
                    @endforeach
                @endif
            </div>
            <div class="ld-legend">
                @foreach ($legend as $status)
                    <span>
                        <x-ld.icon :name="$status->icon()" :size="14" class="ld-tint-{{ $status->color() }}" />{{ $status->label() }}
                        <b>{{ $counts[$status->value] }}</b>
                    </span>
                @endforeach
            </div>
        </section>

        <div>
            <div role="tablist" aria-label="Project views" class="ld-tabs">
                <button type="button" role="tab" class="ld-tab" aria-selected="{{ $tab === 'tasks' ? 'true' : 'false' }}" wire:click="$set('tab', 'tasks')">
                    Tasks <span class="ld-tab-count">{{ $total }}</span>
                </button>
                <button type="button" role="tab" class="ld-tab" aria-selected="{{ $tab === 'modules' ? 'true' : 'false' }}" wire:click="$set('tab', 'modules')">
                    Modules <span class="ld-tab-count">{{ $moduleCount }}</span>
                </button>
                <button type="button" role="tab" class="ld-tab" aria-selected="{{ $tab === 'activity' ? 'true' : 'false' }}" wire:click="$set('tab', 'activity')">
                    Activity
                </button>
            </div>

            <div role="tabpanel">
                @if ($tab === 'tasks')
                    @livewire(\App\Livewire\ProjectTasks::class, ['projectId' => $project->id], key('tasks-'.$project->id))
                @elseif ($tab === 'modules')
                    <div style="padding-top: 16px">
                        @livewire(\App\Livewire\ProjectModules::class, ['projectId' => $project->id], key('modules-'.$project->id))
                    </div>
                @else
                    <div style="padding-top: 16px">
                        @livewire(\App\Livewire\ProjectActivity::class, ['projectId' => $project->id], key('activity-'.$project->id))
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
