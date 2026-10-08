@php
    use App\Support\Dates;
    use App\Enums\TaskStatus;
@endphp

<x-filament-panels::page>
    @if ($empty)
        <div class="ld-page-head">
            <div class="ld-page-titles">
                <h1 class="ld-h1">Today</h1>
            </div>
        </div>

        <x-ld.empty-state
            icon="folder"
            heading="Start with a project"
            body="Projects hold modules and tasks. Your first report appears once you have logged a few updates."
            class="ld-card ld-empty-page"
        >
            <a href="{{ $createProjectUrl }}" class="ld-btn ld-btn-primary">
                <x-ld.icon name="plus" :size="16" />Create project
            </a>
        </x-ld.empty-state>
    @else
        <div class="ld-page-head">
            <div class="ld-page-titles">
                <span class="ld-eyebrow">{{ Dates::eyebrow($today) }}</span>
                <h1 class="ld-h1">Today</h1>
            </div>
            <button type="button" class="ld-btn ld-btn-primary" x-on:click="$dispatch('open-log-update')">
                <x-ld.icon name="plus" :size="16" />Log update<x-ld.kbd class="ld-kbd-on-ink">L</x-ld.kbd>
            </button>
        </div>

        <section aria-labelledby="week-h" class="ld-stack-12">
            <h2 id="week-h" class="ld-section-label">
                This week, {{ Dates::range($week->period->start, $week->period->end, tight: true) }}, compared with last week
            </h2>
            <div class="ld-stat-grid">
                @foreach ($week->headline as $metric)
                    @php
                        $icon = ['completed' => 'status-done', 'started' => 'status-progress', 'blocked' => 'status-blocked', 'overdue' => 'clock'][$metric->key];
                        $tint = ['completed' => 'done', 'started' => 'progress', 'blocked' => 'blocked', 'overdue' => 'danger'][$metric->key];
                        $delta = $metric->delta();
                        $words = $delta === 0
                            ? 'Same as last week'
                            : $metric->arrow().' '.abs($delta).' '.($delta > 0 ? 'more' : 'fewer').' than last week';
                    @endphp
                    <div class="ld-stat">
                        <span class="ld-stat-label">
                            <x-ld.icon :name="$icon" :size="14" class="ld-tint-{{ $tint }}" />{{ $metric->label }}
                        </span>
                        <span class="ld-stat-value">{{ $metric->value }}</span>
                        <span class="ld-stat-delta ld-tone-{{ $metric->tone() }}">{{ $words }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="ld-columns">
            <div class="ld-col-main">
                @if (count($needsUpdate) > 0)
                    <section aria-labelledby="stale-h" class="ld-card">
                        <x-ld.card-header id="stale-h" title="Needs an update" :meta="'In progress, no update for '.$week->staleDays.'+ days'" />
                        <div class="ld-rows">
                            @foreach ($needsUpdate as $item)
                                <div class="ld-row">
                                    <div class="ld-row-main">
                                        <span class="ld-strong">{{ $item->task->title }}</span>
                                        <span class="ld-path">{{ $item->task->path() }}</span>
                                    </div>
                                    <x-ld.status-badge :status="TaskStatus::InProgress" />
                                    <span class="ld-mono-13 ld-tint-blocked ld-w-88">{{ Dates::ago($item->days) }}</span>
                                    <button
                                        type="button"
                                        class="ld-btn ld-btn-outline ld-btn-sm"
                                        aria-label="Log update for {{ $item->task->title }}"
                                        x-on:click="$dispatch('open-log-update', { taskId: {{ $item->task->id }} })"
                                    >Log</button>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                <section aria-labelledby="logged-h" class="ld-card">
                    <x-ld.card-header
                        id="logged-h"
                        title="Logged today"
                        :meta="$loggedToday->count() === 1 ? '1 update' : $loggedToday->count().' updates'"
                    />
                    @if ($loggedToday->isEmpty())
                        <p class="ld-card-empty">Nothing logged yet today. Press <x-ld.kbd>L</x-ld.kbd> to log an update.</p>
                    @else
                        <ol class="ld-events">
                            @foreach ($loggedToday as $event)
                                <x-ld.event-row :event="$event" :time="$event->created_at->format('H:i')" />
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>

            <aside class="ld-col-side">
                <section aria-labelledby="blocked-h" class="ld-card ld-card-pad">
                    <h2 id="blocked-h" class="ld-card-title">Blocked</h2>
                    @forelse ($week->blocked as $item)
                        <div class="ld-blocked-item">
                            <div class="ld-spread">
                                <span class="ld-strong">{{ $item->task->title }}</span>
                                <span class="ld-mono-13 ld-tint-blocked">{{ Dates::days($item->days) }}</span>
                            </div>
                            <span class="ld-path">{{ $item->task->path() }}</span>
                            @if (filled($item->note))
                                <p class="ld-blocker-note">{{ $item->note }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="ld-muted">Nothing is blocked.</p>
                    @endforelse
                </section>

                <section aria-labelledby="due-h" class="ld-card ld-card-pad">
                    <h2 id="due-h" class="ld-card-title">Due and overdue</h2>
                    @if (count($due['rows']) === 0)
                        <p class="ld-muted">No due dates set. Add one when you edit a task.</p>
                    @else
                        <div class="ld-due-list">
                            @foreach ($due['rows'] as $row)
                                <div class="ld-due-row">
                                    <span class="ld-ink">{{ $row['task']->title }}</span>
                                    @if ($row['overdue'] !== null)
                                        <span class="ld-mono-13 ld-medium ld-tint-danger">{{ $row['overdue'] }}d overdue</span>
                                    @else
                                        <span class="ld-mono-13 ld-secondary">{{ Dates::day($row['task']->due_date) }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                        @if ($due['more'])
                            <a href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('index') }}" class="ld-link">Show all</a>
                        @endif
                    @endif
                </section>

                <section aria-labelledby="last-h" class="ld-card ld-card-pad">
                    <h2 id="last-h" class="ld-card-title">Last report</h2>
                    @if ($lastReport)
                        <div class="ld-stack-2">
                            <span class="ld-strong">{{ $lastReport->period_type->label() }} · {{ Dates::range($lastReport->start_date, $lastReport->end_date) }}</span>
                            <span class="ld-mono-13 ld-muted">Generated {{ $lastReport->created_at->format('D j M, H:i') }}</span>
                        </div>
                        <div class="ld-btn-row">
                            @foreach (\App\Enums\ReportFile::cases() as $file)
                                @if ($lastReport->pathFor($file))
                                    <a href="{{ route('filament.app.reports.download', ['report' => $lastReport, 'file' => $file->value]) }}" class="ld-btn ld-btn-outline ld-btn-sm">
                                        <x-ld.icon name="download" :size="14" />{{ $file->shortLabel() }}
                                    </a>
                                @endif
                            @endforeach
                        </div>
                    @else
                        <p class="ld-muted">No reports yet.</p>
                    @endif
                    <a href="{{ \App\Filament\Pages\Reports::getUrl() }}" class="ld-link">Generate this week's report</a>
                </section>
            </aside>
        </div>
    @endif
</x-filament-panels::page>
