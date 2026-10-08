@php
    use App\Domain\Reports\HeadlineMetric;
    use App\Enums\ReportSection;
    use App\Support\Dates;

    /** @var \App\Domain\Reports\ReportData $data */
    $period = $data->period;
    $unit = $period->type->unitLabel();
    $isDaily = $period->start->isSameDay($period->end);
    $tone = fn (int $delta): string => $delta > 0 ? 'ld-tone-success' : 'ld-secondary';
@endphp

<article class="ld-card ld-preview" aria-label="Report preview">
    <header class="ld-stack-4">
        <span class="ld-eyebrow">{{ $period->type->label() }} report · {{ $data->scopeLabel() }}</span>
        <h3 class="ld-report-title">
            {{ $isDaily ? Dates::full($period->start) : Dates::range($period->start, $period->end, year: true) }}
            @if ($period->isPartial())
                <small>(to {{ Dates::day($period->effectiveEnd) }})</small>
            @endif
        </h3>
    </header>

    <p class="ld-summary">{{ $data->summary }}</p>

    <div class="ld-headline">
        @foreach ($data->headline as $metric)
            <div>
                <span class="ld-headline-label">{{ $metric->label }}</span>
                <span class="ld-headline-value">
                    {{ $metric->value }}
                    <small class="ld-tone-{{ $metric->tone() }}" aria-label="{{ $metric->delta() === 0 ? 'same as' : abs($metric->delta()).' '.($metric->delta() > 0 ? 'more' : 'fewer').' than' }} {{ $period->type->previousLabel() }}">{{ $metric->arrow() }}{{ $metric->delta() === 0 ? '' : abs($metric->delta()) }}</small>
                </span>
            </div>
        @endforeach
    </div>

    @if ($data->includes(ReportSection::Completed))
        <section class="ld-report-section">
            <h4 class="ld-report-h"><x-ld.icon name="status-done" :size="16" class="ld-tint-done" />Completed <span class="ld-count">{{ count($data->completed) }}</span></h4>
            @if (count($data->completed) === 0)
                <p class="ld-muted" style="margin: 0">Nothing completed this {{ $unit }}.</p>
            @else
                <ul class="ld-report-list">
                    @foreach ($data->completed as $item)
                        <li>
                            <span><b class="ld-strong">{{ $item->task->title }}</b> <span class="ld-muted">{{ $item->task->path() }}</span></span>
                            <span class="ld-mono-13 ld-secondary">{{ Dates::day($item->date) }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @if ($data->includes(ReportSection::BlockedAndReopened))
        <div class="ld-report-pair">
            <section class="ld-report-section">
                <h4 class="ld-report-h"><x-ld.icon name="status-blocked" :size="16" class="ld-tint-blocked" />Blocked <span class="ld-count">{{ count($data->blocked) }}</span></h4>
                @forelse ($data->blocked as $item)
                    <div class="ld-block-box">
                        <span><b>{{ $item->task->title }}</b> <span>· {{ Dates::days($item->days) }}</span></span>
                        @if (filled($item->note))
                            <span>{{ $item->note }}</span>
                        @endif
                    </div>
                @empty
                    <p class="ld-muted" style="margin: 0">Nothing blocked.</p>
                @endforelse
            </section>
            <section class="ld-report-section">
                <h4 class="ld-report-h"><x-ld.icon name="reopened" :size="16" class="ld-tint-danger" />Reopened <span class="ld-count">{{ count($data->reopened) }}</span></h4>
                @forelse ($data->reopened as $item)
                    <div class="ld-reopen-box">
                        <span><b class="ld-strong">{{ $item->task->title }}</b> <span class="ld-muted">{{ $item->task->path() }}</span></span>
                        <span class="ld-mono-13 ld-secondary">
                            @if ($item->doneDate) Done {{ Dates::short($item->doneDate) }} → @endif In progress, {{ Dates::day($item->reopenedDate) }}
                        </span>
                    </div>
                @empty
                    <p class="ld-muted" style="margin: 0">Nothing reopened.</p>
                @endforelse
            </section>
        </div>
    @endif

    @if ($data->includes(ReportSection::OverdueAndStale))
        <div class="ld-report-pair">
            <section class="ld-report-section">
                <h4 class="ld-report-h">Overdue <span class="ld-count">{{ count($data->overdue) }}</span></h4>
                @forelse ($data->overdue as $item)
                    <div class="ld-kv"><span>{{ $item->task->title }}</span><span class="ld-mono-13 ld-medium ld-tint-danger">{{ $item->daysLate }}d late</span></div>
                @empty
                    <p class="ld-muted" style="margin: 0">Nothing overdue.</p>
                @endforelse
            </section>
            <section class="ld-report-section">
                <h4 class="ld-report-h">No update for {{ $data->staleDays }}+ days <span class="ld-count">{{ count($data->stale) }}</span></h4>
                @forelse ($data->stale as $item)
                    <div class="ld-kv"><span>{{ $item->task->title }}</span><span class="ld-mono-13 ld-tint-blocked">{{ $item->days }}d</span></div>
                @empty
                    <p class="ld-muted" style="margin: 0">Everything in progress was updated recently.</p>
                @endforelse
            </section>
        </div>
    @endif

    @if ($data->includes(ReportSection::ByProject))
        <section class="ld-report-section">
            <h4 class="ld-report-h">By project</h4>
            <div style="overflow-x: auto">
                <table class="ld-num-table">
                    <thead>
                        <tr>
                            <th scope="col">Project</th>
                            <th scope="col">Completed</th>
                            <th scope="col">Started</th>
                            <th scope="col">Blocked</th>
                            <th scope="col">Open</th>
                            <th scope="col">vs {{ $period->type->previousLabel() }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data->byProject as $row)
                            <tr @class(['ld-last-row' => $loop->last])>
                                <th scope="row">{{ $row->name }}</th>
                                <td>{{ $row->completed }}</td>
                                <td>{{ $row->started }}</td>
                                <td>{{ $row->blocked }}</td>
                                <td>{{ $row->open }}</td>
                                <td class="{{ $tone($row->completedDelta) }}">{{ HeadlineMetric::signed($row->completedDelta) }}</td>
                            </tr>
                        @endforeach
                        <tr class="ld-total">
                            <th scope="row">Total</th>
                            <td>{{ $data->total->completed }}</td>
                            <td>{{ $data->total->started }}</td>
                            <td>{{ $data->total->blocked }}</td>
                            <td>{{ $data->total->open }}</td>
                            <td class="{{ $tone($data->total->completedDelta) }}">{{ HeadlineMetric::signed($data->total->completedDelta) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</article>
