@php
    use App\Domain\Reports\HeadlineMetric;
    use App\Enums\ReportSection;
    use App\Support\Dates;

    /** @var \App\Domain\Reports\ReportData $data */
    $period = $data->period;
    $unit = $period->type->unitLabel();
    $icon = fn (string $name): string => 'data:image/svg+xml;base64,'.base64_encode((string) file_get_contents(resource_path("pdf/icons/{$name}.svg")));
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $filename }}</title>
    <style>{!! $css !!}</style>
</head>
<body>
    <div class="footer">
        <table>
            <tr>
                <td>Ledger · {{ $filename }}</td>
                <td class="right">&nbsp;</td>
            </tr>
        </table>
    </div>

    <table class="header">
        <tr>
            <td>
                <div class="eyebrow">{{ mb_strtoupper($period->type->label().' report · '.$data->scopeLabel()) }}</div>
                <h1>{{ Dates::range($period->start, $period->end, year: true) }}</h1>
            </td>
            <td class="meta">
                @if ($period->isPartial())
                    Covers {{ Dates::weekdayRange($period->start, $period->effectiveEnd) }} ({{ $unit }} in progress)<br>
                @else
                    Covers {{ Dates::weekdayRange($period->start, $period->effectiveEnd) }}<br>
                @endif
                Compared with {{ Dates::range($data->previous->start, $data->previous->end) }}<br>
                Generated {{ $data->generatedAt->format('D j M Y, H:i') }}
            </td>
        </tr>
    </table>

    <p class="summary">{{ $data->summary }}</p>

    <table class="headline">
        <tr>
            @foreach ($data->headline as $metric)
                <td @class(['last' => $loop->last])>
                    <div class="secondary">{{ $metric->label }}</div>
                    <div class="value">{{ $metric->value }} <span class="delta"><span class="glyph">{{ $metric->arrow() }}</span> {{ $metric->signedDelta() }}</span></div>
                </td>
            @endforeach
        </tr>
    </table>

    @if ($data->includes(ReportSection::Completed))
        <div class="section">
            <h2 class="with-icon"><img class="h2-img" src="{{ $icon('done') }}" alt="">Completed ({{ count($data->completed) }})</h2>
            @if (count($data->completed) === 0)
                <div class="empty">Nothing completed this {{ $unit }}.</div>
            @else
                <table class="list">
                    <thead>
                        <tr><th>Task</th><th>Project › Module</th><th class="last-col">Completed</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($data->completed as $item)
                            <tr>
                                <td>{{ $item->task->title }}</td>
                                <td class="secondary">{{ $item->task->path() }}</td>
                                <td class="last-col date">{{ Dates::day($item->date) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endif

    @if ($data->includes(ReportSection::BlockedAndReopened))
        <table class="pair section">
            <tr>
                <td class="col col-left">
                    <h2 class="with-icon"><img class="h2-img" src="{{ $icon('blocked') }}" alt="">Blocked ({{ count($data->blocked) }})</h2>
                    @forelse ($data->blocked as $item)
                        <div class="box box-ink">
                            <div><b>{{ $item->task->title }}</b> · {{ $item->task->path() }} · {{ Dates::days($item->days) }}</div>
                            @if (filled($item->note))
                                <div class="text">{{ $item->note }}</div>
                            @endif
                        </div>
                    @empty
                        <div class="empty">Nothing blocked.</div>
                    @endforelse
                </td>
                <td class="col col-right">
                    <h2 class="with-icon"><img class="h2-img" src="{{ $icon('reopened') }}" alt="">Reopened ({{ count($data->reopened) }})</h2>
                    @forelse ($data->reopened as $item)
                        <div class="box box-rule">
                            <div><b>{{ $item->task->title }}</b> · {{ $item->task->path() }}</div>
                            <div class="mono text" style="font-size: 9pt">
                                @if ($item->doneDate) Done {{ Dates::short($item->doneDate) }} <span class="glyph">→</span> @endif In progress, {{ Dates::day($item->reopenedDate) }}
                            </div>
                        </div>
                    @empty
                        <div class="empty">Nothing reopened.</div>
                    @endforelse
                </td>
            </tr>
        </table>
    @endif

    @if ($data->includes(ReportSection::OverdueAndStale))
        <table class="pair section">
            <tr>
                <td class="col col-left">
                    <h2>Overdue ({{ count($data->overdue) }})</h2>
                    @if (count($data->overdue) === 0)
                        <div class="empty">Nothing overdue.</div>
                    @else
                        <table class="kv">
                            @foreach ($data->overdue as $item)
                                <tr>
                                    <td>{{ $item->task->title }}</td>
                                    <td class="value medium">due {{ Dates::short($item->due) }} · {{ $item->daysLate }}d late</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
                <td class="col col-right">
                    <h2>No update for {{ $data->staleDays }}+ days ({{ count($data->stale) }})</h2>
                    @if (count($data->stale) === 0)
                        <div class="empty">Everything in progress was updated recently.</div>
                    @else
                        <table class="kv">
                            @foreach ($data->stale as $item)
                                <tr>
                                    <td>{{ $item->task->title }}</td>
                                    <td class="value">{{ Dates::days($item->days) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @endif
                </td>
            </tr>
        </table>
    @endif

    @if ($data->includes(ReportSection::ByProject))
        <div class="section">
            <h2>By project</h2>
            <table class="numbers">
                <thead>
                    <tr>
                        <th class="first">Project</th><th>Completed</th><th>Started</th><th>Blocked</th><th>Open</th><th class="end">vs {{ $period->type->previousLabel() }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data->byProject as $row)
                        <tr @class(['last-row' => $loop->last])>
                            <td class="first">{{ $row->name }}</td>
                            <td>{{ $row->completed }}</td>
                            <td>{{ $row->started }}</td>
                            <td>{{ $row->blocked }}</td>
                            <td>{{ $row->open }}</td>
                            <td class="end">{{ HeadlineMetric::signed($row->completedDelta) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total">
                        <td class="first">Total</td>
                        <td>{{ $data->total->completed }}</td>
                        <td>{{ $data->total->started }}</td>
                        <td>{{ $data->total->blocked }}</td>
                        <td>{{ $data->total->open }}</td>
                        <td class="end">{{ HeadlineMetric::signed($data->total->completedDelta) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</body>
</html>
