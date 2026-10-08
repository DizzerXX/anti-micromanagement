<?php

declare(strict_types=1);

namespace App\Domain\Reports\Export;

use App\Domain\Reports\BlockedItem;
use App\Domain\Reports\CompletedItem;
use App\Domain\Reports\OverdueItem;
use App\Domain\Reports\ProjectRow;
use App\Domain\Reports\ReopenedItem;
use App\Domain\Reports\ReportData;
use App\Domain\Reports\StaleItem;
use App\Domain\Reports\StartedItem;
use App\Enums\ReportSection;
use App\Support\Dates;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ReportWorkbook implements Export, WithMultipleSheets
{
    public function __construct(private readonly ReportData $data) {}

    /**
     * @return list<ReportSheet>
     */
    public function sheets(): array
    {
        $d = $this->data;
        $period = $d->period;
        $sheets = [];

        $summary = [
            ['Report', $period->type->label().' report · '.$d->scopeLabel()],
            ['Period start', SheetDate::cell($period->start)],
            ['Period end', SheetDate::cell($period->end)],
            ['Covers to', SheetDate::cell($period->effectiveEnd)],
            ['Status', $period->isPartial() ? ucfirst($period->type->unitLabel()).' in progress' : 'Complete'],
            ['Compared with', Dates::range($d->previous->start, $d->previous->end, year: true)],
            ['Generated', SheetDate::cell($d->generatedAt, withTime: true)],
            [null],
            ['Summary', $d->summary],
            [null],
            ['Metric', 'This '.$period->type->unitLabel(), 'Previous', 'Change'],
        ];
        foreach ($d->headline as $metric) {
            $summary[] = [$metric->label, $metric->value, $metric->previous, $metric->delta()];
        }
        $summary[] = ['No update for '.$d->staleDays.'+ days', count($d->stale), null, null];
        $summary[] = ['Reopened', count($d->reopened), null, null];
        $summary[] = ['Updates logged', $d->updates, $d->previousUpdates, $d->updates - $d->previousUpdates];

        $sheets[] = new ReportSheet('Summary', [], $summary, dateColumns: ['B'], dateRows: [2, 3, 4, 7], dateTimeCell: 'B7');

        if ($d->includes(ReportSection::Completed)) {
            $sheets[] = new ReportSheet('Completed', ['Task', 'Project', 'Module', 'Completed'], array_map(
                fn (CompletedItem $i): array => [$i->task->title, $i->task->project, $i->task->module, SheetDate::cell($i->date)],
                $d->completed,
            ), dateColumns: ['D']);
        }

        if ($d->includes(ReportSection::Started)) {
            $sheets[] = new ReportSheet('Started', ['Task', 'Project', 'Module', 'Started'], array_map(
                fn (StartedItem $i): array => [$i->task->title, $i->task->project, $i->task->module, SheetDate::cell($i->date)],
                $d->started,
            ), dateColumns: ['D']);
        }

        if ($d->includes(ReportSection::BlockedAndReopened)) {
            $sheets[] = new ReportSheet('Blocked', ['Task', 'Project', 'Module', 'Blocked since', 'Days blocked', 'Note'], array_map(
                fn (BlockedItem $i): array => [$i->task->title, $i->task->project, $i->task->module, SheetDate::cell($i->since), $i->days, $i->note],
                $d->blocked,
            ), dateColumns: ['D']);
            $sheets[] = new ReportSheet('Reopened', ['Task', 'Project', 'Module', 'Was done on', 'Reopened on'], array_map(
                fn (ReopenedItem $i): array => [$i->task->title, $i->task->project, $i->task->module, $i->doneDate ? SheetDate::cell($i->doneDate) : null, SheetDate::cell($i->reopenedDate)],
                $d->reopened,
            ), dateColumns: ['D', 'E']);
        }

        if ($d->includes(ReportSection::OverdueAndStale)) {
            $sheets[] = new ReportSheet('Overdue', ['Task', 'Project', 'Module', 'Due', 'Days late'], array_map(
                fn (OverdueItem $i): array => [$i->task->title, $i->task->project, $i->task->module, SheetDate::cell($i->due), $i->daysLate],
                $d->overdue,
            ), dateColumns: ['D']);
            $sheets[] = new ReportSheet('Stale', ['Task', 'Project', 'Module', 'Last update', 'Days without update'], array_map(
                fn (StaleItem $i): array => [$i->task->title, $i->task->project, $i->task->module, SheetDate::cell($i->lastUpdate), $i->days],
                $d->stale,
            ), dateColumns: ['D']);
        }

        if ($d->includes(ReportSection::ByProject)) {
            $rows = array_map(
                fn (ProjectRow $r): array => [$r->name, $r->completed, $r->started, $r->blocked, $r->open, $r->completedDelta],
                [...$d->byProject, $d->total],
            );
            $sheets[] = new ReportSheet('By project', ['Project', 'Completed', 'Started', 'Blocked', 'Open', 'Completed vs '.$period->type->previousLabel()], $rows, boldLastRow: true);
        }

        return $sheets;
    }
}
