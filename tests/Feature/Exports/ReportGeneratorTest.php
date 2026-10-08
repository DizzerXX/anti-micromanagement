<?php

declare(strict_types=1);

namespace Tests\Feature\Exports;

use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportGenerator;
use App\Domain\Reports\ReportRequest;
use App\Enums\PeriodType;
use App\Enums\ReportFile;
use App\Enums\ReportSection;
use App\Enums\ReportStatus;
use App\Models\Project;
use App\Models\Report;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Tests\TestCase;

class ReportGeneratorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('reports');
        $this->signInOnMockupDay();
    }

    private function request(?Project $project = null, ?array $files = null, string $date = '2026-10-08', PeriodType $type = PeriodType::Weekly): ReportRequest
    {
        $builder = app(ReportBuilder::class);

        return new ReportRequest(
            $builder->period($type, CarbonImmutable::parse($date)),
            $project,
            ReportSection::cases(),
            $files ?? ReportFile::cases(),
        );
    }

    public function test_generating_the_seeded_week_writes_both_files_and_a_history_row(): void
    {
        $report = app(ReportGenerator::class)->generate($this->request());

        $this->assertSame(ReportStatus::Ready, $report->status);
        $this->assertSame('2026/weekly-2026-W41.pdf', $report->pdf_path);
        $this->assertSame('2026/weekly-2026-W41.xlsx', $report->xlsx_path);
        $this->assertSame('2026-10-08', $report->effective_end_date->toDateString());
        $this->assertTrue($report->isPartial());
        Storage::disk('reports')->assertExists([$report->pdf_path, $report->xlsx_path]);
    }

    public function test_pdf_is_one_a4_page_with_the_report_content(): void
    {
        $report = app(ReportGenerator::class)->generate($this->request(), CarbonImmutable::parse('2026-10-08 14:05'));
        $pdf = Storage::disk('reports')->get($report->pdf_path);

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $pdf), 'one page');
        $this->assertMatchesRegularExpression('#/MediaBox\s*\[0\.?0*\s+0\.?0*\s+595\.28#', $pdf, 'A4 portrait');
        $this->assertStringContainsString('IBMPlexSans', $pdf, 'Plex Sans embedded');
        $this->assertStringContainsString('IBMPlexMono', $pdf, 'Plex Mono embedded');
    }

    public function test_pdf_view_renders_the_mockup_content(): void
    {
        $builder = app(ReportBuilder::class);
        $data = $builder->build($builder->period(PeriodType::Weekly, CarbonImmutable::today()), null, null, CarbonImmutable::parse('2026-10-08 14:05'));

        $html = view('reports.pdf', ['data' => $data, 'filename' => 'weekly-2026-W41.pdf', 'css' => ''])->render();

        foreach ([
            'WEEKLY REPORT · ALL PROJECTS', '5 – 11 Oct 2026', 'Covers Mon 5 – Thu 8 Oct (week in progress)',
            'Compared with 28 Sep – 4 Oct', 'Generated Thu 8 Oct 2026, 14:05',
            '7 tasks completed, 2 more than last week.', 'Completed (7)', 'Blocked (1)', 'Reopened (1)',
            'Overdue (2)', 'No update for 3+ days (3)', 'due 6 Oct · 2d late', 'By project', 'Ledger · weekly-2026-W41.pdf',
        ] as $text) {
            $this->assertStringContainsString(e($text), $html, $text);
        }
    }

    public function test_xlsx_has_the_spec_sheets_and_real_date_cells(): void
    {
        $report = app(ReportGenerator::class)->generate($this->request());
        $path = Storage::disk('reports')->path($report->xlsx_path);

        $book = IOFactory::load($path);

        $this->assertSame(['Summary', 'Completed', 'Started', 'Blocked', 'Reopened', 'Overdue', 'Stale', 'By project'], $book->getSheetNames());

        $completed = $book->getSheetByName('Completed');
        $this->assertSame(['Task', 'Project', 'Module', 'Completed'], [$completed->getCell('A1')->getValue(), $completed->getCell('B1')->getValue(), $completed->getCell('C1')->getValue(), $completed->getCell('D1')->getValue()]);
        $this->assertSame('Weekly diff query', $completed->getCell('A2')->getValue());
        $date = $completed->getCell('D2');
        $this->assertIsNumeric($date->getValue(), 'dates are serial numbers, not strings');
        $this->assertTrue(Date::isDateTime($date));
        $this->assertSame('2026-10-08', Date::excelToDateTimeObject($date->getValue())->format('Y-m-d'));
        $this->assertSame(8, $completed->getHighestRow());

        $summary = $book->getSheetByName('Summary');
        $this->assertTrue(Date::isDateTime($summary->getCell('B2')));
        $this->assertStringStartsWith('7 tasks completed', (string) $summary->getCell('B9')->getValue());

        $byProject = $book->getSheetByName('By project');
        $this->assertSame(['Total', 7, 4, 1, 17, 2], array_values($byProject->rangeToArray('A5:F5', null, true, false)[0]));
    }

    public function test_pdf_failure_keeps_the_spreadsheet_and_marks_the_report_partial(): void
    {
        config(['ledger.force_failure.pdf' => true]);

        $report = app(ReportGenerator::class)->generate($this->request());

        $this->assertSame(ReportStatus::Partial, $report->status);
        $this->assertNull($report->pdf_path);
        $this->assertNotNull($report->xlsx_path);
        Storage::disk('reports')->assertExists($report->xlsx_path);
        Storage::disk('reports')->assertMissing('2026/weekly-2026-W41.pdf');
        $this->assertStringContainsString('pdf: Forced failure', (string) $report->error);
        $this->assertSame([ReportFile::Pdf], $report->failedFiles());

        config(['ledger.force_failure.pdf' => false]);
        $retried = app(ReportGenerator::class)->retry($report, ReportFile::Pdf);

        $this->assertSame(ReportStatus::Ready, $retried->status);
        $this->assertSame('2026/weekly-2026-W41.pdf', $retried->pdf_path);
        $this->assertNull($retried->error);
        Storage::disk('reports')->assertExists($retried->pdf_path);
    }

    public function test_both_files_failing_marks_the_report_failed(): void
    {
        config(['ledger.force_failure.pdf' => true, 'ledger.force_failure.xlsx' => true]);

        $report = app(ReportGenerator::class)->generate($this->request());

        $this->assertSame(ReportStatus::Failed, $report->status);
        $this->assertCount(2, $report->failedFiles());
    }

    public function test_generating_the_same_period_again_keeps_the_old_row_and_file(): void
    {
        $first = app(ReportGenerator::class)->generate($this->request());
        $second = app(ReportGenerator::class)->generate($this->request());

        $this->assertSame(2, Report::query()->count() - 1); // plus the seeded report
        $this->assertSame('2026/weekly-2026-W41-2.pdf', $second->pdf_path);
        Storage::disk('reports')->assertExists([$first->pdf_path, $second->pdf_path]);
    }

    public function test_daily_and_one_project_filenames(): void
    {
        $project = Project::query()->where('name', 'Home Lab')->firstOrFail();

        $daily = app(ReportGenerator::class)->generate($this->request(type: PeriodType::Daily, files: [ReportFile::Xlsx]));
        $scoped = app(ReportGenerator::class)->generate($this->request(project: $project, files: [ReportFile::Pdf]));

        $this->assertSame('2026/daily-2026-10-08.xlsx', $daily->xlsx_path);
        $this->assertNull($daily->pdf_path);
        $this->assertSame(ReportStatus::Ready, $daily->status, 'a file that was not requested is not a failure');
        $this->assertSame('2026/weekly-2026-W41-home-lab.pdf', $scoped->pdf_path);
        $this->assertSame($project->id, $scoped->project_id);
    }

    public function test_archiving_a_project_leaves_old_files_alone(): void
    {
        $project = Project::query()->where('name', 'Home Lab')->firstOrFail();
        $before = app(ReportGenerator::class)->generate($this->request());
        $bytes = Storage::disk('reports')->get($before->xlsx_path);

        $project->update(['archived_at' => now()]);
        $after = app(ReportGenerator::class)->generate($this->request());

        $this->assertSame($bytes, Storage::disk('reports')->get($before->xlsx_path));
        $byProject = IOFactory::load(Storage::disk('reports')->path($after->xlsx_path))->getSheetByName('By project');
        $this->assertSame(['Report Generator', 'Client portal', 'Total'], array_column($byProject->rangeToArray('A2:A4'), 0));
    }

    public function test_download_route_serves_files_to_the_signed_in_user_only(): void
    {
        $report = app(ReportGenerator::class)->generate($this->request());

        $this->get(route('filament.app.reports.download', ['report' => $report, 'file' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=weekly-2026-W41.pdf');

        auth()->logout();
        $this->get(route('filament.app.reports.download', ['report' => $report, 'file' => 'pdf']))->assertRedirect('/login');
    }
}
