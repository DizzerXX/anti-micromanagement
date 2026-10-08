<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Domain\Reports\ReportBuilder;
use App\Domain\Reports\ReportData;
use App\Enums\PeriodType;
use App\Enums\TaskStatus;
use App\Enums\WeekStart;
use App\Models\Project;
use App\Models\TaskEvent;
use App\Services\Settings;
use App\Services\TaskDetailsService;
use App\Services\TaskStatusService;
use Carbon\CarbonImmutable;
use Database\Factories\TaskHistory;
use Database\Seeders\SampleDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        // Thu 8 Oct 2026, week 5–11 Oct (Monday start).
        $this->travelTo(CarbonImmutable::parse('2026-10-08 15:00'));
        $this->project = Project::factory()->create(['name' => 'Alpha']);
    }

    private function weekly(string $date = '2026-10-08', ?Project $project = null): ReportData
    {
        $builder = app(ReportBuilder::class);

        return $builder->build($builder->period(PeriodType::Weekly, CarbonImmutable::parse($date)), $project);
    }

    private function daily(string $date): ReportData
    {
        $builder = app(ReportBuilder::class);

        return $builder->build($builder->period(PeriodType::Daily, CarbonImmutable::parse($date)));
    }

    /**
     * @param  list<object>  $items
     * @return list<string>
     */
    private function titles(array $items): array
    {
        return array_map(fn (object $item): string => $item->task->title, $items);
    }

    private function counts(ReportData $data): array
    {
        return [
            'completed' => count($data->completed),
            'started' => count($data->started),
            'blocked' => count($data->blocked),
            'reopened' => count($data->reopened),
            'overdue' => count($data->overdue),
            'stale' => count($data->stale),
        ];
    }

    public function test_seeded_week_matches_the_mockup_numbers(): void
    {
        $this->project->delete();
        $this->seed(SampleDataSeeder::class);

        $data = $this->weekly();

        $this->assertSame(['completed' => 7, 'started' => 4, 'blocked' => 1, 'reopened' => 1, 'overdue' => 2, 'stale' => 3], $this->counts($data));
        $this->assertSame([7, 5], [$data->metric('completed')->value, $data->metric('completed')->previous]);
        $this->assertSame([4, 5], [$data->metric('started')->value, $data->metric('started')->previous]);
        $this->assertSame([1, 1], [$data->metric('blocked')->value, $data->metric('blocked')->previous]);
        $this->assertSame([2, 1], [$data->metric('overdue')->value, $data->metric('overdue')->previous]);
        $this->assertSame('success', $data->metric('completed')->tone());
        $this->assertSame('muted', $data->metric('started')->tone());
        $this->assertSame('danger', $data->metric('overdue')->tone());

        $this->assertSame(
            ['Weekly diff query', 'Backup rotation script', 'UPS shutdown hook', 'Period window helper', 'PDF download route', 'VLAN for IoT devices', 'Login page'],
            $this->titles($data->completed),
        );
        $this->assertSame(['Router firmware update', 'Report engine tests'], $this->titles($data->overdue));
        $this->assertSame([2, 1], array_map(fn ($i) => $i->daysLate, $data->overdue));
        $this->assertSame(['XLSX export class', 'Reverse proxy for Nextcloud', 'Invoice PDF layout'], $this->titles($data->stale));
        $this->assertSame([5, 4, 3], array_map(fn ($i) => $i->days, $data->stale));
        $this->assertSame('Docker image', $data->blocked[0]->task->title);
        $this->assertSame(3, $data->blocked[0]->days);
        $this->assertSame('Nightly backup check', $data->reopened[0]->task->title);
        $this->assertSame('2026-10-02', $data->reopened[0]->doneDate?->toDateString());
        $this->assertSame('2026-10-07', $data->reopened[0]->reopenedDate->toDateString());

        $rows = array_map(fn ($r) => [$r->name, $r->completed, $r->started, $r->blocked, $r->open, $r->completedDelta], $data->byProject);
        $this->assertSame([
            ['Report Generator', 3, 2, 1, 9, 1],
            ['Home Lab', 3, 1, 0, 5, 2],
            ['Client portal', 1, 1, 0, 3, -1],
        ], $rows);
        $this->assertSame([7, 4, 1, 17, 2], [$data->total->completed, $data->total->started, $data->total->blocked, $data->total->open, $data->total->completedDelta]);

        $this->assertSame(
            '7 tasks completed, 2 more than last week. 4 started. 1 blocked: Docker image, for 3 days. 2 are overdue and 3 have had no update for 3 days or more.',
            $data->summary,
        );
    }

    public function test_current_partial_week_ends_today(): void
    {
        $data = $this->weekly();

        $this->assertSame('2026-10-05', $data->period->start->toDateString());
        $this->assertSame('2026-10-11', $data->period->end->toDateString());
        $this->assertSame('2026-10-08', $data->period->effectiveEnd->toDateString());
        $this->assertTrue($data->period->isPartial());
        $this->assertSame(['2026-09-28', '2026-10-04'], [$data->previous->start->toDateString(), $data->previous->effectiveEnd->toDateString()]);
    }

    public function test_full_past_week(): void
    {
        TaskHistory::make($this->project, 'Done last week', [['in_progress', '2026-09-29'], ['done', '2026-10-02']], createdOn: '2026-09-28');
        TaskHistory::make($this->project, 'Started last week', [['in_progress', '2026-10-01']], createdOn: '2026-09-28');
        TaskHistory::make($this->project, 'Done this week', [['done', '2026-10-06']], createdOn: '2026-09-28');

        $data = $this->weekly('2026-10-01');

        $this->assertFalse($data->period->isPartial());
        $this->assertSame('2026-10-04', $data->period->effectiveEnd->toDateString());
        $this->assertSame(['Done last week'], $this->titles($data->completed));
        $this->assertSame(['Started last week', 'Done last week'], $this->titles($data->started));
        // Previous week (21–27 Sep) had nothing.
        $this->assertSame(1, $data->metric('completed')->delta());
    }

    public function test_single_day(): void
    {
        TaskHistory::make($this->project, 'Done Tuesday', [['in_progress', '2026-10-05'], ['done', '2026-10-06']], createdOn: '2026-10-01');
        TaskHistory::make($this->project, 'Started Tuesday', [['in_progress', '2026-10-06']], createdOn: '2026-10-01');
        TaskHistory::make($this->project, 'Done Monday', [['done', '2026-10-05']], createdOn: '2026-10-01');

        $data = $this->daily('2026-10-06');

        $this->assertSame('2026-10-06', $data->period->start->toDateString());
        $this->assertSame('2026-10-06', $data->period->effectiveEnd->toDateString());
        $this->assertSame(['Done Tuesday'], $this->titles($data->completed));
        $this->assertSame(['Started Tuesday'], $this->titles($data->started));
        $this->assertSame(['2026-10-05', 1], [$data->previous->start->toDateString(), $data->metric('completed')->previous]);
        $this->assertSame('1 task completed. 1 started.', $data->summary, 'equal to yesterday → no comparison');
    }

    public function test_daily_summary_says_yesterday(): void
    {
        TaskHistory::make($this->project, 'One', [['done', '2026-10-06']], createdOn: '2026-10-01');
        TaskHistory::make($this->project, 'Two', [['done', '2026-10-06']], createdOn: '2026-10-01');

        $this->assertStringStartsWith('2 tasks completed, 2 more than yesterday.', $this->daily('2026-10-06')->summary);
    }

    public function test_backdated_event_counts_in_the_period_it_is_logged_for(): void
    {
        $task = TaskHistory::make($this->project, 'Backdated', [['in_progress', '2026-09-29']], createdOn: '2026-09-28');
        // Logged today, about last Friday.
        app(TaskStatusService::class)->log($task, TaskStatus::Done, null, CarbonImmutable::parse('2026-10-02'));

        $this->assertSame([], $this->titles($this->weekly()->completed));
        $this->assertSame(['Backdated'], $this->titles($this->weekly('2026-10-02')->completed));
        $this->assertSame(1, $this->weekly()->metric('completed')->previous);
    }

    public function test_reopened_in_the_same_period_is_not_completed(): void
    {
        TaskHistory::make($this->project, 'Flip flop', [
            ['in_progress', '2026-10-01'],
            ['done', '2026-10-05'],
            ['in_progress', '2026-10-07'],
        ], createdOn: '2026-09-28');

        $data = $this->weekly();

        $this->assertSame([], $data->completed);
        $this->assertSame(['Flip flop'], $this->titles($data->reopened));
        $this->assertSame('2026-10-05', $data->reopened[0]->doneDate?->toDateString());
    }

    public function test_reopened_then_done_again_is_completed_with_last_date(): void
    {
        TaskHistory::make($this->project, 'Done twice', [
            ['done', '2026-10-05'],
            ['in_progress', '2026-10-06'],
            ['done', '2026-10-07'],
        ], createdOn: '2026-09-28');

        $data = $this->weekly();

        $this->assertSame(['Done twice'], $this->titles($data->completed));
        $this->assertSame('2026-10-07', $data->completed[0]->date->toDateString());
        $this->assertSame(['Done twice'], $this->titles($data->reopened));
        $this->assertSame([], $data->started, 'todo → done first, so never "started"');
    }

    public function test_sunday_event_counts_in_that_week_not_the_next(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-12 09:00')); // Monday
        TaskHistory::make($this->project, 'Sunday night', [['done', '2026-10-11 23:59']], createdOn: '2026-10-01');
        TaskHistory::make($this->project, 'Monday morning', [['done', '2026-10-12 00:00']], createdOn: '2026-10-01');

        $this->assertSame(['Sunday night'], $this->titles($this->weekly('2026-10-11')->completed));
        $this->assertSame(['Monday morning'], $this->titles($this->weekly('2026-10-12')->completed));
    }

    public function test_week_start_setting_moves_the_boundary(): void
    {
        app(Settings::class)->update(WeekStart::Sunday, 3);
        TaskHistory::make($this->project, 'Sunday', [['done', '2026-10-04']], createdOn: '2026-10-01');

        $data = $this->weekly('2026-10-08');

        $this->assertSame(['2026-10-04', '2026-10-10'], [$data->period->start->toDateString(), $data->period->end->toDateString()]);
        $this->assertSame(['Sunday'], $this->titles($data->completed));
    }

    public function test_empty_period(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00'));
        TaskHistory::make($this->project, 'Old', [['done', '2026-10-02']], createdOn: '2026-10-01');

        $data = $this->weekly('2026-10-14');

        $this->assertTrue($data->isEmpty());
        $this->assertSame(['completed' => 0, 'started' => 0, 'blocked' => 0, 'reopened' => 0, 'overdue' => 0, 'stale' => 0], $this->counts($data));

        $builder = app(ReportBuilder::class);
        [$active, $updates] = $builder->lastActivePeriodBefore($data->period);
        $this->assertSame('2026-09-28', $active->start->toDateString());
        $this->assertSame(1, $updates);
    }

    public function test_task_created_mid_period_appears_only_in_overdue(): void
    {
        TaskHistory::make($this->project, 'New and late', [], createdOn: '2026-10-06', due: '2026-10-07');
        TaskHistory::make($this->project, 'New', [], createdOn: '2026-10-06');

        $data = $this->weekly();

        $this->assertSame(['completed' => 0, 'started' => 0, 'blocked' => 0, 'reopened' => 0, 'overdue' => 1, 'stale' => 0], $this->counts($data));
        $this->assertSame(['New and late'], $this->titles($data->overdue));
        $this->assertTrue($data->isEmpty(), 'creation events are not updates');
    }

    public function test_due_on_the_end_date_is_not_overdue(): void
    {
        TaskHistory::make($this->project, 'Due today', [], createdOn: '2026-10-01', due: '2026-10-08');

        $this->assertSame([], $this->weekly()->overdue);
    }

    public function test_task_created_after_the_period_is_ignored(): void
    {
        TaskHistory::make($this->project, 'Future task', [], createdOn: '2026-10-06', due: '2026-09-01');

        $data = $this->weekly('2026-10-01');

        $this->assertSame([], $data->overdue);
        $this->assertSame(0, $data->byProject[0]->open);
    }

    public function test_stale_counts_progress_notes_as_updates(): void
    {
        TaskHistory::make($this->project, 'Quiet', [['in_progress', '2026-10-01']], createdOn: '2026-09-28');
        TaskHistory::make($this->project, 'Noted', [['in_progress', '2026-10-01'], ['in_progress', '2026-10-07', 'Still going']], createdOn: '2026-09-28');
        TaskHistory::make($this->project, 'Exactly three', [['in_progress', '2026-10-05']], createdOn: '2026-09-28');
        TaskHistory::make($this->project, 'Two days', [['in_progress', '2026-10-06']], createdOn: '2026-09-28');

        $data = $this->weekly();

        $this->assertSame(['Quiet', 'Exactly three'], $this->titles($data->stale));
        $this->assertSame([7, 3], array_map(fn ($i) => $i->days, $data->stale));
    }

    public function test_blocked_shows_latest_block_note_and_days(): void
    {
        TaskHistory::make($this->project, 'Stuck', [
            ['in_progress', '2026-09-29'],
            ['blocked', '2026-09-30', 'First block'],
            ['in_progress', '2026-10-01'],
            ['blocked', '2026-10-06', 'Second block'],
            ['blocked', '2026-10-07', 'Progress note while blocked'],
        ], createdOn: '2026-09-28');

        $data = $this->weekly();

        $this->assertSame('Second block', $data->blocked[0]->note);
        $this->assertSame(2, $data->blocked[0]->days);
        $this->assertSame('1 blocked: Stuck, for 2 days.', $data->summary);
    }

    public function test_one_project_vs_all_projects(): void
    {
        $other = Project::factory()->create(['name' => 'Beta']);
        TaskHistory::make($this->project, 'Alpha done', [['done', '2026-10-06']], createdOn: '2026-10-01');
        TaskHistory::make($other, 'Beta done', [['done', '2026-10-06']], createdOn: '2026-10-01');

        $all = $this->weekly();
        $one = $this->weekly(project: $other);

        $this->assertSame(['Alpha done', 'Beta done'], $this->titles($all->completed));
        $this->assertCount(2, $all->byProject);
        $this->assertSame(['Beta done'], $this->titles($one->completed));
        $this->assertCount(1, $one->byProject);
        $this->assertSame('Beta', $one->scopeLabel());
        $this->assertSame('All projects', $all->scopeLabel());
    }

    public function test_archived_projects_are_excluded(): void
    {
        $archived = Project::factory()->create(['name' => 'Gone']);
        TaskHistory::make($archived, 'Hidden', [['done', '2026-10-06']], createdOn: '2026-10-01');
        $archived->update(['archived_at' => now()]);

        $data = $this->weekly();

        $this->assertSame([], $data->completed);
        $this->assertSame(['Alpha'], array_map(fn ($r) => $r->name, $data->byProject));
    }

    public function test_summary_singulars_and_omissions(): void
    {
        TaskHistory::make($this->project, 'One done', [['done', '2026-10-06']], createdOn: '2026-10-01');
        TaskHistory::make($this->project, 'Late', [], createdOn: '2026-10-01', due: '2026-10-05');

        $this->assertSame('1 task completed, 1 more than last week. 1 is overdue.', $this->weekly()->summary);
    }

    public function test_summary_when_nothing_moved(): void
    {
        $this->assertSame('Nothing moved that week.', $this->weekly()->summary);
    }

    public function test_summary_fewer_and_stale_only(): void
    {
        TaskHistory::make($this->project, 'Last week', [['done', '2026-10-01']], createdOn: '2026-09-28');
        TaskHistory::make($this->project, 'Idle', [['in_progress', '2026-09-30']], createdOn: '2026-09-28');

        $this->assertSame('No tasks completed, 1 fewer than last week. 1 has had no update for 3 days or more.', $this->weekly()->summary);
    }

    public function test_deleted_tasks_leave_reports_but_keep_their_events(): void
    {
        $task = TaskHistory::make($this->project, 'Gone', [['done', '2026-10-06']], createdOn: '2026-10-01');
        TaskHistory::make($this->project, 'Kept', [['done', '2026-10-06']], createdOn: '2026-10-01');

        app(TaskDetailsService::class)->delete($task);

        $data = $this->weekly();
        $this->assertSame(['Kept'], $this->titles($data->completed));
        $this->assertSame(1, $data->updates);
        $this->assertSame(2, TaskEvent::query()->where('task_id', $task->id)->count());
    }
}
