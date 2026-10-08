<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Domain\InvalidTaskUpdate;
use App\Domain\StatusTimeline;
use App\Enums\TaskStatus;
use App\Models\Module;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskEvent;
use App\Services\TaskStatusService;
use Carbon\CarbonImmutable;
use Database\Factories\TaskHistory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TaskStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaskStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-08 12:00'));
        $this->service = app(TaskStatusService::class);
    }

    private function assertCacheMatchesReplay(Task $task): void
    {
        $task->refresh();
        $timeline = StatusTimeline::forTask($task);

        $this->assertSame($timeline->statusAt(CarbonImmutable::today()), $task->current_status, 'current_status must equal replayed status');
        $this->assertSame($timeline->lastLoggedFor()?->toDateString(), $task->last_logged_for->toDateString(), 'last_logged_for must equal max logged_for');
    }

    private function taskIn(TaskStatus $status): Task
    {
        $steps = match ($status) {
            TaskStatus::Todo => [],
            TaskStatus::InProgress => [['in_progress', '2026-10-01']],
            TaskStatus::Blocked => [['in_progress', '2026-10-01'], ['blocked', '2026-10-02', 'Waiting']],
            TaskStatus::Done => [['done', '2026-10-01']],
        };

        return TaskHistory::make(Project::factory()->create(), 'Task', $steps, createdOn: '2026-09-30');
    }

    public function test_create_writes_initial_event_and_caches(): void
    {
        $project = Project::factory()->create();
        $module = Module::factory()->for($project)->create();

        $task = $this->service->create($project, 'Write tests', $module, CarbonImmutable::parse('2026-10-11'));

        $this->assertSame(TaskStatus::Todo, $task->current_status);
        $this->assertSame('2026-10-08', $task->last_logged_for->toDateString());
        $event = $task->events()->sole();
        $this->assertNull($event->from_status);
        $this->assertSame(TaskStatus::Todo, $event->to_status);
        $this->assertSame('2026-10-08', $event->logged_for->toDateString());
        $this->assertCacheMatchesReplay($task);
    }

    public function test_create_refuses_module_from_another_project(): void
    {
        $module = Module::factory()->create();

        $this->expectException(InvalidTaskUpdate::class);
        $this->service->create(Project::factory()->create(), 'Task', $module);
    }

    /**
     * @return array<string, array{TaskStatus, TaskStatus, ?string}>
     */
    public static function allowedTransitions(): array
    {
        return [
            'todo → in_progress' => [TaskStatus::Todo, TaskStatus::InProgress, null],
            'todo → done' => [TaskStatus::Todo, TaskStatus::Done, null],
            'in_progress → blocked' => [TaskStatus::InProgress, TaskStatus::Blocked, 'Waiting on review'],
            'in_progress → done' => [TaskStatus::InProgress, TaskStatus::Done, null],
            'blocked → in_progress' => [TaskStatus::Blocked, TaskStatus::InProgress, null],
            'blocked → done' => [TaskStatus::Blocked, TaskStatus::Done, null],
            'done → in_progress (reopen)' => [TaskStatus::Done, TaskStatus::InProgress, null],
            'todo note' => [TaskStatus::Todo, TaskStatus::Todo, 'Scoped it'],
            'in_progress note' => [TaskStatus::InProgress, TaskStatus::InProgress, 'Halfway'],
            'blocked note' => [TaskStatus::Blocked, TaskStatus::Blocked, 'Still waiting'],
            'done note' => [TaskStatus::Done, TaskStatus::Done, 'Shipped'],
        ];
    }

    #[DataProvider('allowedTransitions')]
    public function test_allowed_transitions_write_an_event(TaskStatus $from, TaskStatus $to, ?string $note): void
    {
        $task = $this->taskIn($from);
        $before = $task->events()->count();

        $event = $this->service->log($task, $to, $note);

        $this->assertSame($from, $event->from_status);
        $this->assertSame($to, $event->to_status);
        $this->assertSame($before + 1, $task->events()->count());
        $this->assertSame($to, $task->refresh()->current_status);
        $this->assertCacheMatchesReplay($task);
    }

    /**
     * @return array<string, array{TaskStatus, TaskStatus, string}>
     */
    public static function refusedTransitions(): array
    {
        return [
            'todo → blocked' => [TaskStatus::Todo, TaskStatus::Blocked, 'Can’t go from To do to Blocked. Start it first.'],
            'in_progress → todo' => [TaskStatus::InProgress, TaskStatus::Todo, 'Can’t go from In progress to To do.'],
            'blocked → todo' => [TaskStatus::Blocked, TaskStatus::Todo, 'Can’t go from Blocked to To do.'],
            'done → todo' => [TaskStatus::Done, TaskStatus::Todo, 'Can’t go from Done to To do.'],
            'done → blocked' => [TaskStatus::Done, TaskStatus::Blocked, 'Can’t go from Done to Blocked.'],
        ];
    }

    #[DataProvider('refusedTransitions')]
    public function test_refused_transitions_throw_and_write_nothing(TaskStatus $from, TaskStatus $to, string $message): void
    {
        $task = $this->taskIn($from);
        $before = $task->events()->count();

        try {
            $this->service->log($task, $to, 'A note');
            $this->fail('Expected the transition to be refused.');
        } catch (InvalidTaskUpdate $e) {
            $this->assertStringStartsWith($message, $e->getMessage());
            $this->assertSame('status', $e->field);
        }

        $this->assertSame($before, $task->events()->count());
        $this->assertSame($from, $task->refresh()->current_status);
    }

    public function test_blocked_requires_a_note(): void
    {
        $task = $this->taskIn(TaskStatus::InProgress);

        $this->expectExceptionObject(InvalidTaskUpdate::noteRequired(TaskStatus::InProgress, TaskStatus::Blocked));
        $this->service->log($task, TaskStatus::Blocked, '   ');
    }

    public function test_progress_note_requires_a_note(): void
    {
        $task = $this->taskIn(TaskStatus::InProgress);

        try {
            $this->service->log($task, TaskStatus::InProgress, null);
            $this->fail('Expected a note to be required.');
        } catch (InvalidTaskUpdate $e) {
            $this->assertSame('note', $e->field);
        }

        $this->assertSame(2, $task->events()->count());
    }

    public function test_progress_note_helper_keeps_status(): void
    {
        $task = $this->taskIn(TaskStatus::Blocked);

        $event = $this->service->progressNote($task, 'Pinged them again');

        $this->assertTrue($event->isProgressNote());
        $this->assertSame(TaskStatus::Blocked, $task->refresh()->current_status);
        $this->assertCacheMatchesReplay($task);
    }

    public function test_note_longer_than_500_characters_is_refused(): void
    {
        $task = $this->taskIn(TaskStatus::InProgress);

        $this->expectException(InvalidTaskUpdate::class);
        $this->service->log($task, TaskStatus::Done, str_repeat('a', 501));
    }

    public function test_note_of_exactly_500_characters_is_accepted(): void
    {
        $task = $this->taskIn(TaskStatus::InProgress);

        $event = $this->service->log($task, TaskStatus::Done, str_repeat('a', 500));

        $this->assertSame(500, mb_strlen((string) $event->note));
    }

    public function test_future_logged_for_is_refused(): void
    {
        $task = $this->taskIn(TaskStatus::Todo);

        try {
            $this->service->log($task, TaskStatus::InProgress, null, CarbonImmutable::parse('2026-10-09'));
            $this->fail('Expected a future date to be refused.');
        } catch (InvalidTaskUpdate $e) {
            $this->assertSame('logged_for', $e->field);
        }

        $this->assertSame(1, $task->events()->count());
    }

    public function test_logged_for_before_creation_is_refused(): void
    {
        $task = $this->taskIn(TaskStatus::Todo); // created 30 Sep

        $this->expectException(InvalidTaskUpdate::class);
        $this->service->log($task, TaskStatus::InProgress, null, CarbonImmutable::parse('2026-09-29'));
    }

    public function test_backdated_event_between_existing_events_replays_correctly(): void
    {
        // in_progress 1 Oct, done 6 Oct. Backdate a block on 3 Oct.
        $task = TaskHistory::make(Project::factory()->create(), 'Task', [
            ['in_progress', '2026-10-01'],
            ['done', '2026-10-06'],
        ], createdOn: '2026-09-30');

        $event = $this->service->log($task, TaskStatus::Blocked, 'Forgot to log this', CarbonImmutable::parse('2026-10-03'));

        $this->assertSame(TaskStatus::InProgress, $event->from_status, 'from_status is the replayed status on that date');
        $timeline = StatusTimeline::forTask($task);
        $this->assertSame(TaskStatus::Blocked, $timeline->statusAt(CarbonImmutable::parse('2026-10-04')));
        $this->assertSame(TaskStatus::Done, $timeline->statusAt(CarbonImmutable::parse('2026-10-06')));
        $this->assertSame(TaskStatus::Done, $task->refresh()->current_status);
        $this->assertCacheMatchesReplay($task);
    }

    public function test_backdated_event_is_validated_against_status_on_that_date(): void
    {
        // Done today; on 2 Oct it was still To do, so → blocked then is refused.
        $task = TaskHistory::make(Project::factory()->create(), 'Task', [['done', '2026-10-08']], createdOn: '2026-09-30');

        $this->expectExceptionMessage('Can’t go from To do to Blocked.');
        $this->service->log($task, TaskStatus::Blocked, 'Note', CarbonImmutable::parse('2026-10-02'));
    }

    public function test_backdated_event_updates_last_logged_for_only_when_later(): void
    {
        $task = TaskHistory::make(Project::factory()->create(), 'Task', [['in_progress', '2026-10-06']], createdOn: '2026-09-30');

        $this->service->log($task, TaskStatus::InProgress, 'Earlier note', CarbonImmutable::parse('2026-10-02'));

        $this->assertSame('2026-10-06', $task->refresh()->last_logged_for->toDateString());
        $this->assertCacheMatchesReplay($task);
    }

    public function test_undo_deletes_latest_event_within_60_seconds(): void
    {
        $task = $this->taskIn(TaskStatus::InProgress);
        $event = $this->service->log($task, TaskStatus::Done);
        $this->assertSame(TaskStatus::Done, $task->refresh()->current_status);

        $this->travel(59)->seconds();
        $this->service->undo($event);

        $this->assertModelMissing($event);
        $this->assertSame(TaskStatus::InProgress, $task->refresh()->current_status);
        $this->assertSame('2026-10-01', $task->last_logged_for->toDateString());
        $this->assertCacheMatchesReplay($task);
    }

    public function test_undo_after_60_seconds_is_refused(): void
    {
        $task = $this->taskIn(TaskStatus::InProgress);
        $event = $this->service->log($task, TaskStatus::Done);

        $this->travel(60)->seconds();

        $this->expectExceptionMessage('Can’t undo: newer updates exist or too much time passed.');
        try {
            $this->service->undo($event);
        } finally {
            $this->assertModelExists($event);
            $this->assertSame(TaskStatus::Done, $task->refresh()->current_status);
        }
    }

    public function test_undo_when_a_newer_event_exists_is_refused(): void
    {
        $task = $this->taskIn(TaskStatus::InProgress);
        $first = $this->service->log($task, TaskStatus::Blocked, 'Waiting');
        $this->service->log($task, TaskStatus::InProgress);

        $this->expectException(InvalidTaskUpdate::class);
        try {
            $this->service->undo($first);
        } finally {
            $this->assertModelExists($first);
            $this->assertCacheMatchesReplay($task);
        }
    }

    public function test_undo_of_a_backdated_event_recomputes_caches(): void
    {
        $task = TaskHistory::make(Project::factory()->create(), 'Task', [['in_progress', '2026-10-06']], createdOn: '2026-09-30');
        $event = $this->service->log($task, TaskStatus::Done, null, CarbonImmutable::parse('2026-10-07'));
        $this->assertSame(TaskStatus::Done, $task->refresh()->current_status);

        $this->service->undo($event);

        $this->assertSame(TaskStatus::InProgress, $task->refresh()->current_status);
        $this->assertCacheMatchesReplay($task);
    }

    public function test_creation_event_cannot_be_undone(): void
    {
        $task = $this->service->create(Project::factory()->create(), 'Fresh');

        $this->expectException(InvalidTaskUpdate::class);
        $this->service->undo($task->events()->sole());
    }

    public function test_events_are_append_only(): void
    {
        $task = $this->taskIn(TaskStatus::InProgress);
        $event = $task->events()->latest('id')->first();

        $this->expectException(\LogicException::class);
        $event->update(['note' => 'Rewritten']);
    }

    public function test_cache_equals_replay_after_a_mixed_sequence(): void
    {
        $task = $this->taskIn(TaskStatus::Todo);

        $steps = [
            fn () => $this->service->log($task, TaskStatus::InProgress, null, CarbonImmutable::parse('2026-10-02')),
            fn () => $this->service->progressNote($task, 'Note', CarbonImmutable::parse('2026-10-03')),
            fn () => $this->service->log($task, TaskStatus::Blocked, 'Stuck', CarbonImmutable::parse('2026-10-05')),
            fn () => $this->service->log($task, TaskStatus::Done),
            fn () => $this->service->log($task, TaskStatus::InProgress),
        ];

        foreach ($steps as $step) {
            $step();
            $this->assertCacheMatchesReplay($task);
        }

        $last = TaskEvent::query()->where('task_id', $task->id)->latest('id')->first();
        $this->service->undo($last);
        $this->assertSame(TaskStatus::Done, $task->refresh()->current_status);
        $this->assertCacheMatchesReplay($task);
    }

    public function test_deleting_a_module_keeps_its_tasks_in_the_project(): void
    {
        $project = Project::factory()->create();
        $module = Module::factory()->for($project)->create();
        $task = $this->service->create($project, 'Task', $module);

        $module->delete();

        $task->refresh();
        $this->assertNull($task->module_id);
        $this->assertSame($project->id, $task->project_id);
    }
}
