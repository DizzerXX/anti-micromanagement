<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Reports\FailureExplainer;
use App\Domain\Reports\Period;
use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportData;
use App\Domain\Reports\ReportGenerator;
use App\Domain\Reports\ReportRequest;
use App\Enums\PeriodType;
use App\Enums\ReportFile;
use App\Enums\ReportSection;
use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\Report;
use App\Support\Dates;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;

/**
 * Reports › New report and History (HANDOFF §5, mockups/Reports.html,
 * States.html). Generation runs one step per request so the progress bar
 * is real: read updates, compare, build PDF, build spreadsheet.
 */
class Reports extends Page implements HasTable
{
    use InteractsWithTable;

    private const STEPS = 4;

    protected static ?string $title = 'Reports';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.reports';

    #[Url]
    public string $tab = 'new';

    #[Url(as: 'period')]
    public string $periodType = 'weekly';

    /** Start date of the chosen period (Y-m-d). */
    #[Url(as: 'from')]
    public string $start = '';

    public string $scope = 'all';

    public ?int $projectId = null;

    /** @var list<string> */
    public array $sections = [];

    /** @var list<string> */
    public array $files = [];

    // Generation state.
    public bool $generating = false;

    public int $step = 0;

    public string $generatedAt = '';

    /** @var array<string, string> file value => relative path */
    public array $paths = [];

    /** @var array<string, string> file value => error */
    public array $errors = [];

    public int $stepUpdates = 0;

    /** A report row to show the failure state for. */
    public ?int $failedReportId = null;

    public function mount(): void
    {
        $this->sections = ReportSection::values();
        $this->files = array_map(fn (ReportFile $f): string => $f->value, ReportFile::cases());

        if (! in_array($this->tab, ['new', 'history'], true)) {
            $this->tab = 'new';
        }

        $type = PeriodType::tryFrom($this->periodType) ?? PeriodType::Weekly;
        $this->periodType = $type->value;
        $date = rescue(fn () => CarbonImmutable::parse($this->start), CarbonImmutable::today(), false);
        $this->start = $this->builder()->period($type, $date->min(CarbonImmutable::today()))->start->toDateString();
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function updatedPeriodType(): void
    {
        $type = PeriodType::tryFrom($this->periodType) ?? PeriodType::Weekly;
        $this->periodType = $type->value;

        // Keep the same moment in view: a week opens on its last available day.
        $current = $this->period(PeriodType::from($type === PeriodType::Daily ? 'weekly' : 'daily'));
        $anchor = $type === PeriodType::Daily ? $current->effectiveEnd : $current->start;

        $this->start = $this->builder()->period($type, $anchor)->start->toDateString();
        $this->failedReportId = null;
    }

    public function shiftPeriod(int $direction): void
    {
        $period = $this->period();
        $next = $period->shift($direction > 0 ? 1 : -1, CarbonImmutable::today());

        if ($next->isFuture()) {
            return;
        }

        $this->start = $next->start->toDateString();
        $this->failedReportId = null;
    }

    public function goTo(string $start): void
    {
        $this->start = $this->builder()->period($this->type(), CarbonImmutable::parse($start))->start->toDateString();
    }

    #[On('task-logged')]
    public function refreshPreview(): void {}

    // ---------------------------------------------------------- Generate

    public function generate(): void
    {
        $request = $this->request();

        if ($request === null || $this->builder()->updatesIn($request->period, $request->project) === 0) {
            Notification::make()->title('Nothing to generate. Pick a period with updates and at least one file type.')->danger()->send();

            return;
        }

        $this->failedReportId = null;
        $this->generating = true;
        $this->step = 0;
        $this->errors = [];
        $this->generatedAt = CarbonImmutable::now()->toIso8601String();
        $this->paths = $this->generator()->reservePaths($request);
        $this->dispatch('report-step');
    }

    /**
     * Runs the next generation step. Called by the browser after each step,
     * so the bar and step list show real progress.
     */
    public function runStep(): void
    {
        $request = $this->request();

        if (! $this->generating || $request === null) {
            return;
        }

        $data = $this->generator()->data($request, CarbonImmutable::parse($this->generatedAt));

        match ($this->step) {
            0 => $this->stepUpdates = $data->updates,
            1 => null, // Comparison is part of ReportData; the step is shown for clarity.
            2 => $this->buildFile(ReportFile::Pdf, $request, $data),
            3 => $this->buildFile(ReportFile::Xlsx, $request, $data),
            default => null,
        };

        $this->step++;

        if ($this->step < self::STEPS) {
            $this->dispatch('report-step');

            return;
        }

        $this->finish($request);
    }

    public function cancel(): void
    {
        foreach ($this->paths as $path) {
            Storage::disk('reports')->delete($path);
        }

        $this->reset('generating', 'step', 'paths', 'errors', 'stepUpdates');
        Notification::make()->title('Cancelled. Nothing was saved.')->send();
    }

    public function retry(int $reportId, string $file): void
    {
        $report = Report::query()->findOrFail($reportId);
        $type = ReportFile::from($file);

        $this->generator()->retry($report, $type);
        $report->refresh();

        if ($report->pathFor($type) !== null) {
            $this->failedReportId = $report->status === ReportStatus::Ready ? null : $report->id;
            Notification::make()
                ->title($type->label().' built.')
                ->body(basename((string) $report->pathFor($type)).' is in History.')
                ->success()
                ->actions([$this->downloadAction($report, $type)])
                ->send();
        } else {
            $this->failedReportId = $report->id;
            Notification::make()->title($type->label().' failed again. See the technical details.')->danger()->send();
        }
    }

    // ------------------------------------------------------------ History

    public function table(Table $table): Table
    {
        return $table
            ->query(Report::query()->with('project'))
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->columns([
                TextColumn::make('period')
                    ->label('Period')
                    ->state(fn (Report $r): string => $r->period_type->label().' · '.Dates::range($r->start_date, $r->end_date, year: true))
                    ->description(fn (Report $r): ?string => $r->isPartial() ? 'to '.Dates::day($r->effective_end_date).' ('.$r->period_type->unitLabel().' in progress)' : null)
                    ->weight('medium'),
                TextColumn::make('scope')
                    ->label('Scope')
                    ->state(fn (Report $r): string => $r->project->name ?? 'All projects'),
                TextColumn::make('created_at')
                    ->label('Generated')
                    ->dateTime('D j M Y, H:i')
                    ->sortable()
                    ->extraAttributes(['class' => 'ld-col-mono']),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (ReportStatus $state, Report $r): string => match ($state) {
                        ReportStatus::Ready => 'Ready',
                        default => implode(', ', array_map(fn (ReportFile $f) => $f->shortLabel(), $r->failedFiles())).' failed',
                    })
                    ->icon(fn (ReportStatus $state): string => $state === ReportStatus::Ready ? 'ledger-status-done' : 'ledger-warning')
                    ->color(fn (ReportStatus $state): string => $state === ReportStatus::Ready ? 'gray' : 'danger'),
            ])
            ->recordActions([
                Action::make('pdf')
                    ->label('PDF')
                    ->icon('ledger-download')
                    ->color('gray')
                    ->button()
                    ->url(fn (Report $r): ?string => $r->pdf_path ? $this->downloadUrl($r, ReportFile::Pdf) : null)
                    ->visible(fn (Report $r): bool => $r->pdf_path !== null),
                Action::make('xlsx')
                    ->label('XLSX')
                    ->icon('ledger-download')
                    ->color('gray')
                    ->button()
                    ->url(fn (Report $r): ?string => $r->xlsx_path ? $this->downloadUrl($r, ReportFile::Xlsx) : null)
                    ->visible(fn (Report $r): bool => $r->xlsx_path !== null),
                Action::make('retry')
                    ->label(fn (Report $r): string => 'Retry '.($r->failedFiles()[0] ?? ReportFile::Pdf)->shortLabel())
                    ->icon('ledger-retry')
                    ->color('gray')
                    ->button()
                    ->visible(fn (Report $r): bool => $r->failedFiles() !== [])
                    ->action(fn (Report $r) => $this->retry($r->id, $r->failedFiles()[0]->value)),
            ])
            ->emptyStateIcon('ledger-report')
            ->emptyStateHeading('No reports yet')
            ->emptyStateDescription('Generated reports appear here with their PDF and spreadsheet.')
            ->emptyStateActions([
                Action::make('newReport')->label('New report')->color('gray')->action(fn () => $this->tab = 'new'),
            ]);
    }

    // ---------------------------------------------------------------- View

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $period = $this->period();
        $project = $this->project();
        $builder = $this->builder();
        $data = $builder->build($period, $project, $this->selectedSections());
        $empty = $data->isEmpty();
        $failed = $this->failedReportId !== null ? Report::query()->find($this->failedReportId) : null;

        return [
            'period' => $period,
            'data' => $data,
            'empty' => $empty,
            'lastActive' => $empty ? $builder->lastActivePeriodBefore($period, $project) : null,
            'isCurrent' => $period->shift(1, CarbonImmutable::today())->isFuture(),
            'projects' => Project::query()->active()->orderBy('name')->pluck('name', 'id'),
            'canGenerate' => ! $empty && $this->files !== [] && ($this->scope === 'all' || $project !== null),
            'historyCount' => Report::query()->count(),
            'steps' => $this->generating ? $this->stepLabels($data) : [],
            'failed' => $failed,
            'explainer' => app(FailureExplainer::class),
            'generator' => $this->generator(),
        ];
    }

    /**
     * @return list<array{label: string, state: string}>
     */
    private function stepLabels(ReportData $data): array
    {
        $labels = [
            'Read '.($this->stepUpdates ?: $data->updates).' updates, '.Dates::range($data->period->start, $data->period->effectiveEnd),
            'Compared with '.Dates::range($data->previous->start, $data->previous->end),
            in_array('pdf', $this->files, true) ? 'Building PDF' : 'PDF not requested',
            in_array('xlsx', $this->files, true) ? 'Building spreadsheet' : 'Spreadsheet not requested',
        ];

        return array_map(fn (string $label, int $i): array => [
            'label' => $label,
            'state' => $i < $this->step ? 'done' : ($i === $this->step ? 'active' : 'pending'),
        ], $labels, array_keys($labels));
    }

    private function buildFile(ReportFile $file, ReportRequest $request, ReportData $data): void
    {
        if (! $request->wants($file)) {
            return;
        }

        $error = $this->generator()->writeFile($file, $data, $this->paths[$file->value]);

        if ($error !== null) {
            $this->errors[$file->value] = $error;
        }
    }

    private function finish(ReportRequest $request): void
    {
        $report = $this->generator()->record($request, $this->paths, $this->errors, CarbonImmutable::parse($this->generatedAt));

        $this->reset('generating', 'step', 'paths', 'errors', 'stepUpdates');

        if ($report->status !== ReportStatus::Ready) {
            $this->failedReportId = $report->id;

            return;
        }

        Notification::make()
            ->title('Report saved.')
            ->body(collect($report->files)->map(fn (string $f) => basename((string) $report->pathFor(ReportFile::from($f))))->implode(' and ').' are in History.')
            ->success()
            ->duration(8000)
            ->actions(array_map(fn (string $f): Action => $this->downloadAction($report, ReportFile::from($f)), $report->files))
            ->send();
    }

    private function downloadAction(Report $report, ReportFile $file): Action
    {
        return Action::make('download-'.$file->value)
            ->label($file->shortLabel())
            ->button()
            ->color('gray')
            ->url($this->downloadUrl($report, $file));
    }

    private function downloadUrl(Report $report, ReportFile $file): string
    {
        return route('filament.app.reports.download', ['report' => $report, 'file' => $file->value]);
    }

    private function request(): ?ReportRequest
    {
        $files = array_values(array_filter(array_map(fn (string $f) => ReportFile::tryFrom($f), $this->files)));
        $project = $this->project();

        if ($files === [] || ($this->scope === 'one' && $project === null)) {
            return null;
        }

        return new ReportRequest($this->period(), $project, $this->selectedSections(), $files);
    }

    /**
     * @return list<ReportSection>
     */
    private function selectedSections(): array
    {
        return array_values(array_filter(array_map(fn (string $s) => ReportSection::tryFrom($s), $this->sections)));
    }

    private function project(): ?Project
    {
        return $this->scope === 'one' && $this->projectId !== null
            ? Project::query()->active()->find($this->projectId)
            : null;
    }

    private function type(): PeriodType
    {
        return PeriodType::tryFrom($this->periodType) ?? PeriodType::Weekly;
    }

    private function period(?PeriodType $type = null): Period
    {
        $date = rescue(fn () => CarbonImmutable::parse($this->start), CarbonImmutable::today(), false);

        return $this->builder()->period($type ?? $this->type(), $date);
    }

    private function builder(): ReportBuilder
    {
        return app(ReportBuilder::class);
    }

    private function generator(): ReportGenerator
    {
        return app(ReportGenerator::class);
    }
}
