@php
    use App\Domain\Reports\HeadlineMetric;
    use App\Enums\PeriodType;
    use App\Enums\ReportFile;
    use App\Enums\ReportSection;
    use App\Support\Dates;

    $type = $period->type;
    $unit = $type->unitLabel();
@endphp

<x-filament-panels::page>
    <div class="ld-stack-24" style="gap: 20px">
        <h1 class="ld-h1">Reports</h1>

        <div role="tablist" aria-label="Reports views" class="ld-tabs">
            <button type="button" role="tab" class="ld-tab" aria-selected="{{ $tab === 'new' ? 'true' : 'false' }}" wire:click="$set('tab', 'new')">New report</button>
            <button type="button" role="tab" class="ld-tab" aria-selected="{{ $tab === 'history' ? 'true' : 'false' }}" wire:click="$set('tab', 'history')">
                History <span class="ld-tab-count">{{ $historyCount }}</span>
            </button>
        </div>

        @if ($tab === 'history')
            <div role="tabpanel">{{ $this->table }}</div>
        @else
            <div class="ld-report-layout" role="tabpanel">
                {{-- Options --}}
                <form aria-label="Report options" class="ld-card ld-report-form" wire:submit="generate">
                    <fieldset class="ld-fieldset" style="gap: 8px">
                        <legend class="ld-legend-title">Period</legend>
                        <div class="ld-segmented">
                            @foreach (PeriodType::cases() as $option)
                                <label>
                                    <input type="radio" name="period" value="{{ $option->value }}" wire:model.live="periodType" @disabled($generating) />{{ $option->label() }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <div class="ld-field">
                        <span id="period-label" class="ld-label">{{ $type === PeriodType::Weekly ? 'Week' : 'Day' }}</span>
                        <div role="group" aria-labelledby="period-label" class="ld-stepper">
                            <button type="button" class="ld-stepper-btn" aria-label="Previous {{ $unit }}" wire:click="shiftPeriod(-1)" @disabled($generating)>
                                <x-ld.icon name="chevron-left" :size="16" />
                            </button>
                            <span class="ld-stepper-value" aria-live="polite">{{ Dates::weekdayRange($period->start, $period->end) }}</span>
                            <button type="button" class="ld-stepper-btn" aria-label="Next {{ $unit }}" wire:click="shiftPeriod(1)" @disabled($isCurrent || $generating)>
                                <x-ld.icon name="chevron-right" :size="16" />
                            </button>
                        </div>
                        <span class="ld-help">Compared with {{ Dates::range($data->previous->start, $data->previous->end) }}</span>
                    </div>

                    <fieldset class="ld-fieldset">
                        <legend class="ld-legend-title">Scope</legend>
                        <label class="ld-check ld-radio"><input type="radio" name="scope" value="all" wire:model.live="scope" />All projects</label>
                        <label class="ld-check ld-radio"><input type="radio" name="scope" value="one" wire:model.live="scope" />One project</label>
                        @if ($scope === 'one')
                            <label for="report-project" class="ld-sr-only">Project</label>
                            <select id="report-project" class="ld-select" wire:model.live="projectId">
                                <option value="">Pick a project</option>
                                @foreach ($projects as $id => $name)
                                    <option value="{{ $id }}">{{ $name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </fieldset>

                    <fieldset class="ld-fieldset">
                        <legend class="ld-legend-title">Sections</legend>
                        @foreach (ReportSection::cases() as $section)
                            <label class="ld-check"><input type="checkbox" value="{{ $section->value }}" wire:model.live="sections" />{{ $section->label() }}</label>
                        @endforeach
                    </fieldset>

                    <fieldset class="ld-fieldset">
                        <legend class="ld-legend-title">Files</legend>
                        <label class="ld-check"><input type="checkbox" value="pdf" wire:model.live="files" />PDF</label>
                        <label class="ld-check"><input type="checkbox" value="xlsx" wire:model.live="files" />Spreadsheet (.xlsx)</label>
                        @if ($files === [])
                            <span class="ld-error">Pick at least one file type to generate.</span>
                        @endif
                    </fieldset>

                    @if ($period->isPartial())
                        <div role="note" class="ld-callout">
                            <x-ld.icon name="note" :size="16" />
                            <span>This {{ $unit }} isn’t over. The report covers {{ Dates::weekdayRange($period->start, $period->effectiveEnd) }} and says so on every file.</span>
                        </div>
                    @endif

                    <button
                        type="submit"
                        @class(['ld-btn ld-btn-block', 'ld-btn-primary' => ! $failed, 'ld-btn-outline' => (bool) $failed])
                        @disabled(! $canGenerate || $generating)
                        @if (! $canGenerate) aria-describedby="generate-why" @endif
                    >Generate report</button>
                    @if (! $canGenerate && ! $generating)
                        <span id="generate-why" class="ld-help">
                            @if ($empty) Nothing was logged in this {{ $unit }}.
                            @elseif ($files === []) Pick a file type above.
                            @else Pick a project above.
                            @endif
                        </span>
                    @endif
                </form>

                {{-- Preview / generating / failure --}}
                <section aria-labelledby="preview-h" class="ld-preview-col" x-data x-on:report-step.window="$wire.runStep()">
                    <div class="ld-preview-head">
                        <h2 id="preview-h">Preview</h2>
                        <span>Updates as you change options</span>
                    </div>

                    @if ($generating)
                        @php $done = collect($steps)->where('state', 'done')->count(); @endphp
                        <div class="ld-card ld-state" aria-live="polite">
                            <h2 class="ld-empty-heading">Generating report</h2>
                            <div class="ld-progress" role="progressbar" aria-valuemin="0" aria-valuemax="4" aria-valuenow="{{ $done }}" aria-label="Report generation, step {{ min($done + 1, 4) }} of 4">
                                <div style="width: {{ max(4, (int) round(($done + 0.5) / 4 * 100)) }}%"></div>
                            </div>
                            <ol class="ld-steps">
                                @foreach ($steps as $s)
                                    <li data-state="{{ $s['state'] }}">
                                        @if ($s['state'] === 'done')
                                            <x-ld.icon name="status-done" :size="18" class="ld-tint-done" />
                                        @elseif ($s['state'] === 'active')
                                            <x-ld.icon name="spinner" :size="18" class="ld-spin" />
                                        @else
                                            <x-ld.icon name="status-todo" :size="18" />
                                        @endif
                                        {{ $s['label'] }}
                                    </li>
                                @endforeach
                            </ol>
                            <button type="button" class="ld-btn ld-btn-outline" style="align-self: flex-start" wire:click="cancel">Cancel</button>
                        </div>
                    @elseif ($failed)
                        @php
                            $failedFiles = $failed->failedFiles();
                            $first = $failedFiles[0] ?? ReportFile::Pdf;
                            $kept = collect(ReportFile::cases())->first(fn ($f) => $failed->pathFor($f) !== null);
                        @endphp
                        <div class="ld-card ld-state" style="gap: 16px">
                            <div class="ld-failure-label">
                                <x-ld.icon name="warning" :size="22" />
                                <span>{{ collect($failedFiles)->map(fn ($f) => $f->shortLabel())->implode(' and ') }} failed</span>
                            </div>
                            <h2 class="ld-empty-heading">
                                @if ($kept)
                                    {{ $kept->label() }} saved. {{ $first->shortLabel() }} didn’t build.
                                @else
                                    The report didn’t build.
                                @endif
                            </h2>
                            <p style="margin: 0">
                                {{ $explainer->explain($first, $generator->errorFor($failed, $first)) }}
                                @if ($kept) The {{ mb_strtolower($kept->label()) }} is already in History. @endif
                            </p>
                            <div class="ld-btn-row">
                                <button type="button" class="ld-btn ld-btn-primary" wire:click="retry({{ $failed->id }}, '{{ $first->value }}')" wire:loading.attr="disabled">
                                    Retry {{ $first->shortLabel() }}
                                </button>
                                @if ($kept)
                                    <a class="ld-btn ld-btn-outline" href="{{ route('filament.app.reports.download', ['report' => $failed, 'file' => $kept->value]) }}">
                                        Download {{ mb_strtolower($kept->label()) }}
                                    </a>
                                @endif
                            </div>
                            <details class="ld-details">
                                <summary>Technical details</summary>
                                <pre>{{ $failed->error }}</pre>
                            </details>
                        </div>
                    @elseif ($empty)
                        <div class="ld-card">
                            <x-ld.empty-state
                                icon="report"
                                :heading="'Nothing logged '.Dates::range($period->start, $period->end)"
                                body="Reports are built from the updates you log. Log one first, or open a {{ $unit }} that has activity."
                                style="min-height: 420px"
                            >
                                <div class="ld-btn-row" style="justify-content: center">
                                    <button type="button" class="ld-btn ld-btn-outline" x-on:click="$dispatch('open-log-update')">Log update</button>
                                    @if ($lastActive)
                                        <button type="button" class="ld-btn ld-link" style="background: none; border: 0; padding: 0 6px" wire:click="goTo('{{ $lastActive[0]->start->toDateString() }}')">
                                            Go to {{ Dates::range($lastActive[0]->start, $lastActive[0]->end) }} ({{ $lastActive[1] }} {{ $lastActive[1] === 1 ? 'update' : 'updates' }})
                                        </button>
                                    @endif
                                </div>
                            </x-ld.empty-state>
                        </div>
                    @else
                        @include('reports.preview', ['data' => $data])
                    @endif
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
