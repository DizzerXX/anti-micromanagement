<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Enums\ReportStatus;
use App\Filament\Pages\Reports;
use App\Filament\Pages\Settings;
use App\Models\Report;
use App\Services\Settings as SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('reports');
        $this->signInOnMockupDay();
    }

    public function test_preview_shows_the_current_week(): void
    {
        Livewire::test(Reports::class)
            ->assertSet('start', '2026-10-05')
            ->assertSee('Mon 5 – Sun 11 Oct')
            ->assertSee('Compared with 28 Sep – 4 Oct')
            ->assertSee('This week isn’t over. The report covers Mon 5 – Thu 8 Oct and says so on every file.')
            ->assertSee('Weekly report · All projects')
            ->assertSee('(to Thu 8 Oct)')
            ->assertSee('7 tasks completed, 2 more than last week. 4 started. 1 blocked: Docker image, for 3 days. 2 are overdue and 3 have had no update for 3 days or more.')
            ->assertSeeInOrder(['Completed', 'Blocked', 'Reopened', 'Overdue', 'No update for 3+ days', 'By project']);
    }

    public function test_next_is_blocked_at_the_current_period_and_previous_moves_back(): void
    {
        Livewire::test(Reports::class)
            ->call('shiftPeriod', 1)
            ->assertSet('start', '2026-10-05')
            ->call('shiftPeriod', -1)
            ->assertSet('start', '2026-09-28')
            ->assertSee('Mon 28 Sep – Sun 4 Oct')
            ->assertDontSee('isn’t over');
    }

    public function test_daily_period_and_sections_toggle_the_preview(): void
    {
        Livewire::test(Reports::class)
            ->set('periodType', 'daily')
            ->assertSet('start', '2026-10-08')
            ->assertSee('Thu 8 Oct 2026')
            ->assertSee('vs yesterday')
            ->set('sections', ['completed'])
            ->assertDontSee('vs yesterday')
            ->assertDontSee('No update for 3+ days');
    }

    public function test_empty_period_shows_the_empty_state_and_disables_generate(): void
    {
        Livewire::test(Reports::class)
            ->set('start', '2026-08-03')
            ->assertSee('Nothing logged 3 – 9 Aug')
            ->assertSee('Reports are built from the updates you log.')
            ->assertSeeHtml('disabled')
            ->call('generate')
            ->assertNotified();

        $this->assertSame(1, Report::query()->count());
    }

    public function test_generating_runs_each_step_then_records_history(): void
    {
        $component = Livewire::test(Reports::class)
            ->call('generate')
            ->assertSet('generating', true)
            ->assertSee('Generating report')
            ->assertDispatched('report-step');

        $component->call('runStep')->assertSee('Building PDF');
        $component->call('runStep')->call('runStep')->call('runStep')
            ->assertSet('generating', false)
            ->assertNotified('Report saved.');

        $report = Report::query()->latest('id')->firstOrFail();
        $this->assertSame(ReportStatus::Ready, $report->status);
        Storage::disk('reports')->assertExists([$report->pdf_path, $report->xlsx_path]);
    }

    public function test_cancel_removes_partly_written_files(): void
    {
        $component = Livewire::test(Reports::class)->call('generate');
        $component->call('runStep')->call('runStep')->call('runStep'); // PDF written
        $pdf = $component->get('paths')['pdf'];
        Storage::disk('reports')->assertExists($pdf);

        $component->call('cancel')->assertSet('generating', false);

        Storage::disk('reports')->assertMissing($pdf);
        $this->assertSame(1, Report::query()->count());
    }

    public function test_partial_failure_state_and_retry(): void
    {
        config(['ledger.force_failure.pdf' => true]);

        $component = Livewire::test(Reports::class)->call('generate');
        foreach (range(1, 4) as $_) {
            $component->call('runStep');
        }

        $component->assertSee('PDF failed')
            ->assertSee('Spreadsheet saved. PDF didn’t build.')
            ->assertSee('Retry PDF')
            ->assertSee('Download spreadsheet')
            ->assertSee('Technical details');

        $report = Report::query()->latest('id')->firstOrFail();
        $this->assertSame(ReportStatus::Partial, $report->status);

        config(['ledger.force_failure.pdf' => false]);
        $component->call('retry', $report->id, 'pdf')->assertDontSee('PDF failed');

        $this->assertSame(ReportStatus::Ready, $report->refresh()->status);
    }

    public function test_history_lists_reports_newest_first(): void
    {
        Livewire::test(Reports::class)
            ->set('tab', 'history')
            ->assertCanSeeTableRecords(Report::all())
            ->assertSee('Weekly · 28 Sep – 4 Oct 2026');
    }

    public function test_settings_save_changes_week_start_and_stale_days(): void
    {
        Livewire::test(Settings::class)
            ->fillForm(['week_start' => 'sunday', 'stale_days' => 5])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Settings saved.');

        $settings = app(SettingsStore::class);
        $this->assertSame('sunday', $settings->weekStart()->value);
        $this->assertSame(5, $settings->staleDays());

        Livewire::test(Settings::class)
            ->fillForm(['stale_days' => 0])
            ->call('save')
            ->assertHasFormErrors(['stale_days']);
    }
}
